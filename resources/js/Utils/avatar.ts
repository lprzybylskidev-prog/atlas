export const DEFAULT_AVATAR_COLOR = '#0f766e';

export function readableAvatarTextColor(hex: string | null | undefined): '#18181b' | '#ffffff' {
    const normalized = typeof hex === 'string' && /^#[0-9A-Fa-f]{6}$/.test(hex) ? hex.slice(1) : DEFAULT_AVATAR_COLOR.slice(1);
    const red = Number.parseInt(normalized.slice(0, 2), 16);
    const green = Number.parseInt(normalized.slice(2, 4), 16);
    const blue = Number.parseInt(normalized.slice(4, 6), 16);
    const luminance = (0.2126 * red + 0.7152 * green + 0.0722 * blue) / 255;

    return luminance > 0.62 ? '#18181b' : '#ffffff';
}
