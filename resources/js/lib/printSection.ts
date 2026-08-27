type PrintSectionOptions = {
    title?: string;
    printDelayMs?: number;
};

type ExportSectionPdfOptions = {
    title?: string;
    fileName?: string;
    scale?: number;
    marginMm?: number;
    paperWidthPx?: number;
    orientation?: 'portrait' | 'landscape';
    excludeSelectors?: string[];
    revealSelectors?: string[];
    pageBreakSelectors?: string[];
    includeDefaultHeading?: boolean;
};

export function printSectionById(sectionId: string, options: PrintSectionOptions = {}): void {
    const source = document.getElementById(sectionId);

    if (!source) {
        return;
    }

    const printWindow = window.open('', '_blank', 'width=1200,height=900');

    if (!printWindow) {
        return;
    }

    const title = options.title ?? document.title;
    const styleLinks = Array.from(document.querySelectorAll<HTMLLinkElement>('link[rel="stylesheet"]'))
        .map((link) => `<link rel="stylesheet" href="${link.href}" />`)
        .join('');

    const inlineStyles = Array.from(document.querySelectorAll<HTMLStyleElement>('style'))
        .map((style) => style.outerHTML)
        .join('');

    const html = `<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>${title}</title>
    ${styleLinks}
    ${inlineStyles}
    <style>
        .print-hide {
            display: none !important;
        }
    </style>
</head>
<body style="margin: 0; padding: 16px; background: #fff;">
    ${source.outerHTML}
</body>
</html>`;

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();

    const printDelayMs = options.printDelayMs ?? 500;
    let didPrint = false;

    const triggerPrint = () => {
        if (didPrint || printWindow.closed) {
            return;
        }

        didPrint = true;
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    };

    // Primary path: wait for the new window to fully load rendered content.
    printWindow.addEventListener(
        'load',
        () => {
            window.setTimeout(triggerPrint, printDelayMs);
        },
        { once: true },
    );

    // Fallback path: some browsers may miss/skip the load event for about:blank writes.
    window.setTimeout(triggerPrint, 1500);
}

export async function downloadSectionAsPdfById(sectionId: string, options: ExportSectionPdfOptions = {}): Promise<void> {
    const source = document.getElementById(sectionId);

    if (!source) {
        console.error(`[downloadSectionAsPdfById] Section element #${sectionId} not found.`);
        return;
    }

    const title = options.title ?? document.title ?? 'report';
    const fileName = options.fileName ?? `${slugify(title)}.pdf`;
    const scale = options.scale ?? 1.5;
    const marginMm = options.marginMm ?? 8;
    const paperWidthPx = options.paperWidthPx ?? 820;
    const forcedOrientation = options.orientation;
    const excludeSelectors = options.excludeSelectors ?? ['.print-hide'];
    const revealSelectors = options.revealSelectors ?? [];
    const pageBreakSelectors = options.pageBreakSelectors ?? [];
    const includeDefaultHeading = options.includeDefaultHeading ?? (pageBreakSelectors.length === 0);

    const captureRoot = createPdfCaptureRoot(source, {
        title,
        paperWidthPx,
        excludeSelectors,
        revealSelectors,
        includeDefaultHeading,
    });

    try {
        await waitForRenderableAssets(captureRoot);

        const [{ default: html2canvas }, { jsPDF }] = await Promise.all([import('html2canvas'), import('jspdf')]);
        const pageNodes = pageBreakSelectors.length > 0
            ? pageBreakSelectors.flatMap((selector) => Array.from(captureRoot.querySelectorAll<HTMLElement>(selector)))
            : [captureRoot];

        const validPageNodes = pageNodes.length > 0 ? pageNodes : [captureRoot];

        // Render canvases in parallel for maximum speed
        const canvases = await Promise.all(
            validPageNodes.map((node) => renderPdfCanvas(html2canvas, node, scale, paperWidthPx))
        );

        const firstCanvas = canvases[0];
        const orientation = forcedOrientation ?? (firstCanvas.width > firstCanvas.height ? 'landscape' : 'portrait');
        const pdf = new jsPDF({
            orientation,
            unit: 'mm',
            format: 'a4',
            compress: true,
        });

        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const usablePageHeight = pageHeight - marginMm * 2;
        const contentWidth = pageWidth - marginMm * 2;

        canvases.forEach((canvas, index) => {
            if (index > 0) {
                pdf.addPage();
            }

            const image = canvas.toDataURL('image/jpeg', 0.95);
            const rawContentHeight = (canvas.height * contentWidth) / canvas.width;

            // If using structured pages or within 12% tolerance, fit onto exactly one single page
            if (pageBreakSelectors.length > 0 || rawContentHeight <= usablePageHeight * 1.12) {
                const finalHeight = Math.min(rawContentHeight, usablePageHeight);
                const finalWidth = (canvas.width * finalHeight) / canvas.height;
                const offsetX = marginMm + Math.max(0, (contentWidth - finalWidth) / 2);
                pdf.addImage(image, 'JPEG', offsetX, marginMm, finalWidth, finalHeight, undefined, 'FAST');
            } else {
                // Continuous document multi-page slicing
                pdf.addImage(image, 'JPEG', marginMm, marginMm, contentWidth, rawContentHeight, undefined, 'FAST');
                let renderedHeight = usablePageHeight;
                while (renderedHeight + 10 < rawContentHeight) {
                    pdf.addPage();
                    const offsetY = marginMm - renderedHeight;
                    pdf.addImage(image, 'JPEG', marginMm, offsetY, contentWidth, rawContentHeight, undefined, 'FAST');
                    renderedHeight += usablePageHeight;
                }
            }
        });

        pdf.save(fileName);
    } catch (error) {
        console.error('[downloadSectionAsPdfById] Failed to render PDF canvas:', error);
        // Fallback: print directly using the browser print system
        printSectionById(sectionId, { title });
    } finally {
        captureRoot.remove();
    }
}

async function renderPdfCanvas(
    html2canvas: typeof import('html2canvas').default,
    source: HTMLElement,
    scale: number,
    paperWidthPx: number,
): Promise<HTMLCanvasElement> {
    return html2canvas(source, {
        backgroundColor: '#ffffff',
        scale,
        useCORS: true,
        allowTaint: true,
        windowWidth: paperWidthPx,
        width: source.offsetWidth || paperWidthPx,
        logging: false,
    });
}

function createPdfCaptureRoot(
    source: HTMLElement,
    options: {
        title: string;
        paperWidthPx: number;
        excludeSelectors: string[];
        revealSelectors: string[];
        includeDefaultHeading?: boolean;
    },
): HTMLDivElement {
    const root = document.createElement('div');
    root.setAttribute('data-pdf-capture-root', 'true');
    root.style.position = 'fixed';
    root.style.left = '0';
    root.style.top = '0';
    root.style.width = `${options.paperWidthPx}px`;
    root.style.padding = '0';
    root.style.background = '#ffffff';
    root.style.boxSizing = 'border-box';
    root.style.zIndex = '-99999';
    root.style.pointerEvents = 'none';

    const clonedSection = source.cloneNode(true) as HTMLElement;
    clonedSection.style.width = '100%';
    clonedSection.style.maxWidth = '100%';
    clonedSection.style.boxSizing = 'border-box';

    options.excludeSelectors.forEach((selector) => {
        clonedSection.querySelectorAll<HTMLElement>(selector).forEach((element) => element.remove());
    });

    options.revealSelectors.forEach((selector) => {
        const elements = [
            ...(clonedSection.matches(selector) ? [clonedSection] : []),
            ...Array.from(clonedSection.querySelectorAll<HTMLElement>(selector)),
        ];

        elements.forEach((element) => {
            element.classList.remove('hidden');
            element.style.display = 'block';
            element.style.visibility = 'visible';
            element.style.opacity = '1';
        });
    });

    clonedSection.querySelectorAll<HTMLElement>('.pdf-page').forEach((element) => {
        element.classList.remove('hidden');
        element.style.display = 'block';
        element.style.visibility = 'visible';
        element.style.opacity = '1';
    });

    const style = document.createElement('style');
    style.textContent = `
        [data-pdf-capture-root], [data-pdf-capture-root] * { box-sizing: border-box; }
        [data-pdf-capture-root] [class*="overflow-x-auto"],
        [data-pdf-capture-root] [class*="overflow-auto"] {
            overflow: visible !important;
        }
        [data-pdf-capture-root] table {
            width: 100% !important;
            table-layout: fixed;
            border-collapse: collapse;
        }
        [data-pdf-capture-root] th,
        [data-pdf-capture-root] td {
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        [data-pdf-capture-root] .pdf-page {
            box-shadow: none !important;
            margin: 0 !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
    `;

    root.appendChild(style);

    if (options.includeDefaultHeading) {
        const heading = document.createElement('div');
        heading.style.padding = '16px';
        heading.style.marginBottom = '12px';
        heading.innerHTML = `
            <h1 style="margin: 0; font-size: 20px; font-weight: 700; color: #0f172a;">${escapeHtml(options.title)}</h1>
            <p style="margin: 4px 0 0; font-size: 12px; color: #64748b;">Generated ${new Date().toLocaleString()}</p>
        `;
        root.appendChild(heading);
    }

    root.appendChild(clonedSection);
    document.body.appendChild(root);

    return root;
}

async function waitForRenderableAssets(root: HTMLElement): Promise<void> {
    if ('fonts' in document) {
        try {
            await Promise.race([
                document.fonts.ready,
                new Promise((resolve) => setTimeout(resolve, 800)),
            ]);
        } catch {
            // no-op
        }
    }

    const images = Array.from(root.querySelectorAll('img'));
    if (images.length === 0) {
        return;
    }

    await Promise.all(
        images.map((image) => {
            if (image.complete && image.naturalWidth > 0) {
                return Promise.resolve();
            }

            return new Promise<void>((resolve) => {
                const timer = setTimeout(resolve, 800);
                const onDone = () => {
                    clearTimeout(timer);
                    resolve();
                };

                if (typeof image.decode === 'function') {
                    image.decode().then(onDone).catch(onDone);
                } else {
                    image.onload = onDone;
                    image.onerror = onDone;
                }
            });
        }),
    );
}

function escapeHtml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function slugify(value: string): string {
    return value
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '') || 'report';
}
