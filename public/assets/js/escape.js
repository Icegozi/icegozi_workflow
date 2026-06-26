/**
 * Hàm escape HTML dùng chung, chống stored XSS khi chèn dữ liệu do người dùng
 * kiểm soát (tên, bình luận, mô tả, tên file...) vào DOM qua .html()/.append().
 *
 * Dùng: escapeHtml(value) -> trả về chuỗi an toàn để nội suy vào HTML.
 */
(function (window) {
    function escapeHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Chỉ đặt nếu chưa có, để không ghi đè định nghĩa cục bộ sẵn có.
    if (typeof window.escapeHtml !== 'function') {
        window.escapeHtml = escapeHtml;
    }
})(window);
