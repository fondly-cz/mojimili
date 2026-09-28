/**
 * Human readable file size, e.g. "12 kB" or "3,4 MB".
 */
export const formatSize = (bytes) => {
    if (bytes >= 1024 * 1024)
        return `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB`;
    return `${Math.max(1, Math.round(bytes / 1024))} kB`;
};
