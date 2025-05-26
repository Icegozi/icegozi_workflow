$(document).ready(function () {
    $(document).ready(function () {
        $('#userSelect').select2({
            placeholder: "Chọn người dùng...",
            allowClear: true,
            minimumInputLength: 0,
            language: {
                inputTooShort: function () {
                    return "Nhập ít nhất 1 ký tự...";
                },
                noResults: function () {
                    return "Tài khoản không tồn tại!";
                },
                searching: function () {
                    return "Đang tìm kiếm...";
                }
            },
            ajax: {
                url: window.routeUrls.userList,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        search: params.term
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results
                    };
                },
                cache: true
            }
        });

        // Gắn sự kiện khi chọn user -> chuyển trang
        $('#userSelect').on('change', function () {
            const selectedUrl = $(this).val();
            if (selectedUrl) {
                window.location.href = selectedUrl;
            }
        });
    });


   // Xử lý sự kiện click vào nút xóa
    $(document).on('click', '.delete-user', function () {
        if (!confirm('Bạn có chắc chắn muốn xóa người dùng này?')) {
            return;
        }

        var userId = $(this).data('id');

        var deleteUrl = window.routeUrls.userDestroyBase.replace(':userIdPlaceholder', userId);

        $.ajax({
            url: deleteUrl,
            method: 'DELETE',
            data: {
                _token: window.csrfToken 
            },
            success: function (response) {
                if (response.success) {
                    $('a[data-id="' + userId + '"]').closest('tr').remove();
                    alert(response.message);
                } else {
                    alert(response.message);
                }
            },
            error: function () {
                alert('Không thể tự xóa tài khoản đang sử dụng!');
            }
        });
    });
});
