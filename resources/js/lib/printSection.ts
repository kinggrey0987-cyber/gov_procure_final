type PrintSectionOptions = {
    title?: string;
};

export function printSectionById(sectionId: string, options: PrintSectionOptions = {}): void {
    const source = document.getElementById(sectionId);

    if (!source) {
        return;
    }

    const printWindow = window.open('', '_blank', 'noopener,noreferrer,width=1200,height=900');

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
<body>
    ${source.outerHTML}
</body>
</html>`;

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();

    printWindow.onload = () => {
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    };
}
