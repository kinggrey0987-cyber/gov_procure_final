type PrintSectionOptions = {
    title?: string;
    printDelayMs?: number;
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
