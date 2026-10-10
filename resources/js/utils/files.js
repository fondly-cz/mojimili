/**
 * Human readable file size, e.g. "12 kB" or "3,4 MB".
 */
export const formatSize = (bytes) => {
    if (bytes >= 1024 * 1024)
        return `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB`;
    return `${Math.max(1, Math.round(bytes / 1024))} kB`;
};

/**
 * Images written straight into rich text (an <img> in a description, comment or document),
 * shaped like attachments so the gallery can show them next to uploaded files.
 */
export const inlineImages = (html) => {
    if (!html || !html.includes('<img')) return [];

    return [...new DOMParser().parseFromString(html, 'text/html').images]
        .filter((image) => image.getAttribute('src'))
        .map((image) => {
            const url = image.getAttribute('src');
            const name = decodeURIComponent(url.split(/[?#]/)[0].split('/').pop() || '') || 'obrázek';

            return { url, original_name: name, caption: image.alt || null, is_image: true, inline: true };
        });
};

/**
 * Click handler for a block of rendered rich text: hands the clicked inline image to the callback.
 */
export const onInlineImageClick = (open) => (event) => {
    if (event.target.tagName !== 'IMG') return;

    event.preventDefault();
    open(event.target.getAttribute('src'));
};
