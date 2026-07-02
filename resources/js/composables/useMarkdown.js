// Bộ render Markdown tối giản, AN TOÀN (escape HTML trước, chỉ sinh các thẻ
// có kiểm soát) — dùng chung cho phần mô tả & bình luận kiểu Redmine/Vgate.
// Hỗ trợ: heading, đậm/nghiêng/gạch ngang, code (inline + khối), danh sách
// (ul/ol), trích dẫn, đường link, ngắt dòng.

// Ký tự "canh" thuộc vùng private-use, không xuất hiện trong nội dung thường
// và không bị escapeHtml đụng tới -> dùng làm placeholder cho code.
const SENTINEL = '';

const escapeHtml = (s) =>
    s.replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

// Định dạng trong dòng (text ĐÃ được escape). Code inline được tách trước ở
// bước khối nên ở đây không cần lo dấu backtick.
const inline = (text) => {
    let t = text;
    // Đậm: **x** hoặc __x__
    t = t.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    t = t.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    // Nghiêng: *x* hoặc _x_
    t = t.replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
    t = t.replace(/(^|[^_])_([^_\n]+)_/g, '$1<em>$2</em>');
    // Gạch ngang: ~~x~~
    t = t.replace(/~~([^~]+)~~/g, '<del>$1</del>');
    // Link dạng [text](url) — chỉ cho http/https/mailto
    t = t.replace(
        /\[([^\]]+)\]\((https?:\/\/[^\s)]+|mailto:[^\s)]+)\)/g,
        '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>'
    );
    // Tự động nhận link trần http(s)://…
    t = t.replace(
        /(^|[\s(])(https?:\/\/[^\s<)]+)/g,
        '$1<a href="$2" target="_blank" rel="noopener noreferrer">$2</a>'
    );
    return t;
};

export function renderMarkdown(src) {
    if (!src) return '';

    let text = escapeHtml(String(src).replace(/\r\n/g, '\n'));

    // Tách khối code ```...``` ra placeholder để không bị các phép biến đổi khác đụng vào
    const blocks = [];
    text = text.replace(/```([\s\S]*?)```/g, (_m, code) => {
        blocks.push(code.replace(/^\n/, '').replace(/\n$/, ''));
        return `${SENTINEL}B${blocks.length - 1}${SENTINEL}`;
    });
    // Tách code inline `...`
    const inlines = [];
    text = text.replace(/`([^`\n]+)`/g, (_m, code) => {
        inlines.push(code);
        return `${SENTINEL}I${inlines.length - 1}${SENTINEL}`;
    });

    // Dòng chỉ chứa placeholder của khối code -> tách thành block riêng
    const blockLineRe = new RegExp(`^${SENTINEL}B\\d+${SENTINEL}$`);

    const lines = text.split('\n');
    let html = '';
    let listType = null;
    let inQuote = false;
    let para = [];

    const flushPara = () => {
        if (para.length) {
            html += `<p>${para.map(inline).join('<br>')}</p>`;
            para = [];
        }
    };
    const closeList = () => {
        if (listType) { html += `</${listType}>`; listType = null; }
    };
    const closeQuote = () => {
        if (inQuote) { html += '</blockquote>'; inQuote = false; }
    };

    for (const line of lines) {
        let m;
        // Khối code (placeholder chiếm trọn dòng)
        if (blockLineRe.test(line.trim())) {
            flushPara(); closeList(); closeQuote();
            html += line.trim();
            continue;
        }
        // Heading: # .. ######
        if ((m = line.match(/^(#{1,6})\s+(.*)$/))) {
            flushPara(); closeList(); closeQuote();
            const lvl = m[1].length;
            html += `<h${lvl}>${inline(m[2])}</h${lvl}>`;
            continue;
        }
        // Đường kẻ ngang
        if (/^\s*---+\s*$/.test(line)) {
            flushPara(); closeList(); closeQuote();
            html += '<hr>';
            continue;
        }
        // Trích dẫn (dấu > đã bị escape thành &gt;)
        if ((m = line.match(/^&gt;\s?(.*)$/))) {
            flushPara(); closeList();
            if (!inQuote) { html += '<blockquote>'; inQuote = true; }
            html += inline(m[1]) + '<br>';
            continue;
        }
        closeQuote();
        // Danh sách không thứ tự
        if ((m = line.match(/^\s*[-*+]\s+(.*)$/))) {
            flushPara();
            if (listType !== 'ul') { closeList(); html += '<ul>'; listType = 'ul'; }
            html += `<li>${inline(m[1])}</li>`;
            continue;
        }
        // Danh sách có thứ tự
        if ((m = line.match(/^\s*\d+\.\s+(.*)$/))) {
            flushPara();
            if (listType !== 'ol') { closeList(); html += '<ol>'; listType = 'ol'; }
            html += `<li>${inline(m[1])}</li>`;
            continue;
        }
        closeList();
        // Dòng trống -> kết thúc đoạn
        if (/^\s*$/.test(line)) { flushPara(); continue; }
        // Dòng thường -> gộp vào đoạn hiện tại
        para.push(line);
    }
    flushPara(); closeList(); closeQuote();

    // Khôi phục code
    const bRe = new RegExp(`${SENTINEL}B(\\d+)${SENTINEL}`, 'g');
    const iRe = new RegExp(`${SENTINEL}I(\\d+)${SENTINEL}`, 'g');
    html = html.replace(bRe, (_m, i) => `<pre><code>${blocks[i]}</code></pre>`);
    html = html.replace(/<p>(<pre>[\s\S]*?<\/pre>)<\/p>/g, '$1');
    html = html.replace(iRe, (_m, i) => `<code>${inlines[i]}</code>`);

    return html;
}
