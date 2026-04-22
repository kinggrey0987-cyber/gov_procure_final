type PrintSectionOptions = {
    title?: string;
    printDelayMs?: number;
};

type ExportSectionPdfOptions = {
    title?: string;
    fileName?: string;
    scale?: number;
    marginMm?: number;
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
        return;
    }

    const title = options.title ?? document.title ?? 'report';
    const fileName = options.fileName ?? `${slugify(title)}.pdf`;
    const scale = options.scale ?? Math.min(window.devicePixelRatio || 1, 2);
    const marginMm = options.marginMm ?? 8;

    const [{ default: html2canvas }, { jsPDF }] = await Promise.all([import('html2canvas'), import('jspdf')]);

    const canvas = await html2canvas(source, {
        backgroundColor: '#ffffff',
        scale,
        useCORS: true,
    });

    const orientation = canvas.width > canvas.height ? 'landscape' : 'portrait';
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
    const contentHeight = (canvas.height * contentWidth) / canvas.width;
    const image = canvas.toDataURL('image/png');

    pdf.addImage(image, 'PNG', marginMm, marginMm, contentWidth, contentHeight, undefined, 'FAST');

    let renderedHeight = usablePageHeight;
    while (renderedHeight < contentHeight) {
        pdf.addPage();
        const offsetY = marginMm - renderedHeight;
        pdf.addImage(image, 'PNG', marginMm, offsetY, contentWidth, contentHeight, undefined, 'FAST');
        renderedHeight += usablePageHeight;
    }

    pdf.save(fileName);
}

function slugify(value: string): string {
    return value
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '') || 'report';
}
