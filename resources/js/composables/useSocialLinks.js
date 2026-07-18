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

// Ảnh đại diện luôn lấy từ profile. Khi người dùng chưa tải ảnh, dùng placeholder
// nội bộ thay vì ảnh ngẫu nhiên từ dịch vụ bên ngoài.
export const avatarSrc = (url) => url || '/images/default-avatar.svg';
