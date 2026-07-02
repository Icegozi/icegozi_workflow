// Danh sách nền tảng mạng xã hội hỗ trợ (khớp HandlesProfileMedia::SOCIAL_KEYS
// và luật validate social.* ở backend). Dùng chung cho trang Hồ sơ & form Admin.
export const SOCIAL_PLATFORMS = [
    { key: 'facebook', label: 'Facebook', icon: 'fab fa-facebook', placeholder: 'https://facebook.com/...' },
    { key: 'twitter', label: 'X (Twitter)', icon: 'fab fa-x-twitter', placeholder: 'https://x.com/...' },
    { key: 'linkedin', label: 'LinkedIn', icon: 'fab fa-linkedin', placeholder: 'https://linkedin.com/in/...' },
    { key: 'github', label: 'GitHub', icon: 'fab fa-github', placeholder: 'https://github.com/...' },
    { key: 'website', label: 'Website', icon: 'fas fa-globe', placeholder: 'https://...' },
];

// Tạo object social rỗng {facebook:'', twitter:'', ...} và trộn giá trị sẵn có.
export const makeSocialForm = (existing = {}) =>
    Object.fromEntries(SOCIAL_PLATFORMS.map((p) => [p.key, existing?.[p.key] || '']));

// Ảnh đại diện hiển thị: dùng avatar_url nếu có, ngược lại fallback pravatar theo seed.
export const avatarSrc = (url, seed = 'x', size = 80) =>
    url || `https://i.pravatar.cc/${size}?u=${encodeURIComponent(seed || 'x')}`;
