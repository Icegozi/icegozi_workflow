function createBoardCardHtml(board) {
    let roleLabel = '';
    switch (board.currentUserRole) {
        case 'board_member_manager':
            roleLabel = 'người quản lý';
            break;
        case 'board_editor':
            roleLabel = 'người chỉnh sửa';
            break;
        case 'board_viewer':
            roleLabel = 'người xem';
            break;
        default:
            roleLabel = 'người sở hữu';
    }

    return `
        <div class="col-md-4 col-lg-3 mt-2 mb-2 card-drop-target board-card" id="board-card-${board.id}"
            style="position: relative; overflow: visible; z-index: 1; height:120px">
            <div class="card shadow-sm h-80 card-hover">
                <div class="card-body p-3 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h6 class="mb-0 text-truncate font-weight-bold board-name">${board.name}</h6>
                        <div class="dropdown">
                            <a href="#" class="text-muted dropdown-toggle-no-caret"
                                id="itemMenu${board.id}" data-toggle="dropdown" aria-haspopup="true"
                                aria-expanded="false" aria-label="Tùy chọn bảng">
                                <i class="fas fa-ellipsis-v"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right"
                                style="z-index: 9999 !important; position: absolute !important; overflow: visible !important;  height: 100px !important; font-size: 10px !important"
                                aria-labelledby="itemMenu${board.id}">
                                <a class="dropdown-item open-board-link" href="${board.url_show}">
                                    <i class="fas fa-folder-open fa-fw mr-2 text-muted"></i>Mở
                                </a>
                                <a class="dropdown-item rename-board-link" href="#" data-id="${board.id}" data-name="${board.name}" data-update-url="${board.url_update}">
                                    <i class="fas fa-pencil-alt fa-fw mr-2 text-muted"></i>Sửa tên
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item delete-board-link text-danger" href="#" data-id="${board.id}" data-name="${board.name}" data-destroy-url="${board.url_destroy}">
                                    <i class="fas fa-trash-alt fa-fw mr-2"></i> Xoá
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center text-muted small board-timestamp mt-auto">
                        <div class="d-flex align-items-center">
                            <i class="far fa-clock fa-fw mr-2"></i>
                            <span>${board.updated_at_formatted || board.created_at_formatted}</span>
                        </div>
                        <b class="ml-2">${roleLabel}</b>
                    </div>
                </div>
            </div>
        </div>
    `;
}


$(document).ready(function () {

    // CSRF Token Setup for AJAX (Important!) 
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // 1. create New Board
    $('#add-board-btn').on('click', function () {
        const boardName = prompt("Nhập tên bảng mới:", "");

        if (boardName && boardName.trim() !== "") {
            $.ajax({
                url: window.routeUrls.boardsStore, 
                method: 'POST',
                data: {
                    name: boardName.trim()
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success && response.board) {
                        const newBoardHtml = createBoardCardHtml(response.board);
                        $('#no-boards-message').remove();
                        $('#board-list-container').prepend(newBoardHtml);
                        alert(response.message);
                    } else {
                        alert(response.message || 'Không thể tạo bảng. Phản hồi không hợp lệ.');
                    }
                },
                error: function (xhr) {
                    console.error("Create Error XHR:", xhr); // Debug log
                    let errorMessage = 'Đã xảy ra lỗi khi tạo bảng.';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.errors && xhr.responseJSON.errors.name) {
                            errorMessage = xhr.responseJSON.errors.name[0];
                        } else if (xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                    } else {
                        errorMessage = `Lỗi không xác định (HTTP ${xhr.status}: ${xhr.statusText})`;
                    }
                    console.error("Error response Text:", xhr.responseText);
                    alert(errorMessage);
                }
            });
        } else if (boardName !== null) {
            alert("Tên bảng không được để trống.");
        }
    });

    // 2. rename Board (Modal Trigger) 
    $('#board-list-container').on('click', '.rename-board-link', function (e) {
        e.preventDefault();
        const boardId = $(this).data('id');
        const currentName = $(this).data('name');
        const updateUrl = $(this).data('update-url');

        $('#renameBoardModal').find('#rename-board-id').val(boardId);
        $('#renameBoardModal').find('#rename-board-current-name').val(currentName);
        $('#renameBoardModal').find('#rename-board-new-name').val(currentName);
        $('#renameBoardModal').find('#rename-board-form').attr('action', updateUrl);
        $('#renameBoardModal').modal('show');
        // Optional: Add focus logic after modal is shown
        $('#renameBoardModal').on('shown.bs.modal', function () {
            $('#rename-board-new-name', this).focus().select();
        }).on('hidden.bs.modal', function () {
            // Important: Remove the event listener once hidden to prevent multiple bindings
            $(this).off('shown.bs.modal');
        });
    });

    // --- RENAME Board (Modal Form Submission) ---
    $('#rename-board-form').on('submit', function (e) {
        e.preventDefault();
        const boardId = $('#rename-board-id').val();
        const newName = $('#rename-board-new-name').val().trim();
        const currentName = $('#rename-board-current-name').val();
        const updateUrl = $(this).attr('action');

        if (newName && newName !== currentName) {
            performRenameAjax(boardId, newName, updateUrl);
        } else if (!newName) {
            alert("Tên bảng không được để trống.");
            // Consider showing error within the modal instead of alert
        } else {
            $('#renameBoardModal').modal('hide'); 
        }
    });

    // --- RENAME Board (AJAX Call) ---
    function performRenameAjax(boardId, newName, updateUrl) {
        $.ajax({
            url: updateUrl,
            method: 'PUT',
            data: { name: newName },
            dataType: 'json',
            success: function (response) {
                console.log("Update Success Response:", response); // Debug log
                if (response.success) {
                    const $card = $(`#board-card-${boardId}`);
                    $card.find('.board-name').text(response.new_name);
                    $card.find('.rename-board-link').data('name', response.new_name);
                    $card.find('.delete-board-link').data('name', response.new_name); 

                    if (response.updated_at_formatted) {
                        $card.find('.board-timestamp span').text(response.updated_at_formatted);
                    }
                    $('#renameBoardModal').modal('hide');
                    
                } else {
                    alert(response.message || 'Không thể đổi tên bảng.');
                    
                }
            },
            error: function (xhr) {
                console.error("Update Error XHR:", xhr); // Debug log
                let errorMessage = 'Đã xảy ra lỗi khi đổi tên bảng.';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.errors && xhr.responseJSON.errors.name) {
                        errorMessage = xhr.responseJSON.errors.name[0];
                    } else if (xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                } else {
                    errorMessage = `Lỗi không xác định (HTTP ${xhr.status}: ${xhr.statusText})`;
                }
                console.error("Error response Text:", xhr.responseText);
                alert(errorMessage);
                // Consider showing error within the modal
            }
        });
    }

    // --- 3. DELETE Board ---
    $('#board-list-container').on('click', '.delete-board-link', function (e) {
        e.preventDefault();
        const boardId = $(this).data('id');
        const boardName = $(this).data('name');
        const destroyUrl = $(this).data('destroy-url');

        if (confirm(`Bạn có chắc chắn muốn xoá bảng "${boardName}" không? Hành động này không thể hoàn tác.`)) {
            $.ajax({
                url: destroyUrl,
                method: 'DELETE',
                dataType: 'json',
                success: function (response) {
                    console.log("Delete Success Response:", response); // Debug log
                    if (response.success) {
                        $(`#board-card-${boardId}`).remove();
                        if ($('#board-list-container .board-card').length === 0) {
                            $('#board-list-container').html(`
                                     <div class="col-12" id="no-boards-message">
                                         <p class="text-muted text-center mt-5">Bạn chưa có bảng làm việc nào. Hãy tạo một bảng mới!</p>
                                     </div>
                                 `);
                        }
                        alert(response.message);
                    } else {
                        alert(response.message || 'Không thể xoá bảng.');
                    }
                },
                error: function (xhr) {
                    console.error("Delete Error XHR:", xhr); // Debug log
                    let errorMessage = 'Đã xảy ra lỗi khi xoá bảng.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else {
                        errorMessage = `Lỗi không xác định (HTTP ${xhr.status}: ${xhr.statusText})`;
                    }
                    console.error("Error response Text:", xhr.responseText);
                    alert(errorMessage);
                }
            });
        }
    });


    // ===========================================
    // ========== SHEEP/OVERLAY LOGIC ============
    // ===========================================
    let isDragging = false;
    let offsetX = 0, offsetY = 0;
    let $sheepWrapper = $(".sheep-wrapper"); 

    if ($sheepWrapper.length) { 
        let originalPosition = {
            top: $sheepWrapper.css("top"),
            left: $sheepWrapper.css("left")
        };

        if (typeof $.fn.draggable === 'function') {
            $sheepWrapper.draggable({
                containment: "window",
                cursor: "grabbing", 
                start: function (event, ui) {
                    isDragging = true;
                },
                stop: function (event, ui) {
                    isDragging = false;
                    $sheepWrapper.css("cursor", "grab"); 
                    
                    let sheepOffset = ui.helper.offset();
                    let sheepWidth = ui.helper.outerWidth();
                    let sheepHeight = ui.helper.outerHeight();

                    $("#board-list-container .card-drop-target").each(function () {
                        let $card = $(this);
                        let cardOffset = $card.offset();
                        let cardWidth = $card.outerWidth();
                        let cardHeight = $card.outerHeight();

                        let collision = !(
                            sheepOffset.left + sheepWidth < cardOffset.left ||
                            sheepOffset.left > cardOffset.left + cardWidth ||
                            sheepOffset.top + sheepHeight < cardOffset.top ||
                            sheepOffset.top > cardOffset.top + cardHeight
                        );

                        if (collision) {
                            let boardId = $card.attr("id").replace("board-card-", "");
                            showOverlayAndHideSheep(boardId);
                            return false; 
                        }
                    });
                }
            });
        } else {
            console.warn("jQuery UI Draggable not loaded. Sheep cannot be dragged.");
        }

        function showOverlayAndHideSheep(boardId) {
            $sheepWrapper.hide();

            if ($("#cardOverlay").length === 0) {
                $('body').append('<div id="cardOverlay" style="display: none;"></div>');
            }

            // Gọi API để lấy dữ liệu tổng quan của board
            $.ajax({
                url: window.routeUrls.boardOverview.replace(':boardIdPlaceholder', boardId),
                method: 'GET',
                success: function (response) {
                    if (response.success) {
                        const board_name = response.board_name;
                        const assignees = response.assignees;
                        const totalColumns = response.total_columns;
                        const totalTasks = response.total_tasks;
                        const assigneeRows = assignees.map(user => `
                            <tr>
                                <td><img src="${user.avatar_url}" alt="${user.name}" class="rounded-circle" width="30" height="30"></td>
                                <td>${user.name}</td>
                                <td>${user.email}</td>
                            </tr>
                        `).join('');

                        const overlayHtml = `
                            <div class="overlay-content p-2 bg-white shadow rounded" id="ovl_content" style="
                                min-width: 400px;
                                max-height: 90vh;
                                overflow-y: auto;
                                font-size: 12px;
                            ">
                           
                           <div id="dragHeader" class="bg-dark text-white rounded px-2 py-2 d-flex justify-content-between align-items-center cursor-move">
                                <h6 class="mb-0">${board_name}</h6>
                                <i id="close" class="fa-solid fa-xmark text-danger fs-4" style="cursor: pointer;"></i>
                            </div>

                            <p class="text-left mt-2">Số lượng</p>
                                <table class="table table-bordered table-sm">
                                    <tbody>
                                        <tr>
                                            <th scope="row" class="text-left">Tổng số cột</th>
                                            <td>${totalColumns}</td>
                                        </tr>
                                        <tr>
                                            <th scope="row" class="text-left">Tổng số thẻ công việc</th>
                                            <td>${totalTasks}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <p class="mt-3 text-left">Thành viên tham gia</p>
                                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                    <table class="table table-striped table-sm mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Avatar</th>
                                                <th>Tên</th>
                                                <th>Email</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${assigneeRows}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        `;


                        $("#cardOverlay").html(overlayHtml);

                        requestAnimationFrame(function () {
                            const ovlContent = $("#ovl_content");
                            const ovlTop = window.innerHeight / 2 - ovlContent.outerHeight() / 2;
                            const ovlLeft = window.innerWidth / 2 - ovlContent.outerWidth() / 2;

                            ovlContent.css({
                                position: "fixed",
                                top: ovlTop + "px",
                                left: ovlLeft + "px",
                                zIndex: 1050
                            });

                            ovlContent.draggable({ handle: "#dragHeader" });
                        });

                        $("#cardOverlay").fadeIn();
                    } else {
                        alert("Không lấy được dữ liệu tổng quan.");
                    }
                },
                error: function () {
                    alert("Có lỗi xảy ra khi gọi API.");
                }
            });
        }


        

        $(document).on("click", "#close", function () {
            $("#cardOverlay").fadeOut(function () {
                $sheepWrapper.css({
                    top: originalPosition.top,
                    left: originalPosition.left
                }).show();
            });
        });

        // Click sheep to spin
        $sheepWrapper.on("click", function () {
            if (isDragging) return; // Don't spin if dragging just finished

            const $sheep = $(this).find(".sheep"); // Make sure '.sheep' exists inside '.sheep-wrapper'
            if ($sheep.length === 0) return;

            $sheep.stop(true, true).css("transform", "rotate(0deg)");

            $({ deg: 0 }).animate({ deg: 360 }, {
                duration: 600, // Faster spin?
                easing: 'linear', // Consistent spin speed
                step: function (now) {
                    $sheep.css("transform", "rotate(" + now + "deg)");
                },
                complete: function () {
                    $sheep.css("transform", "none"); // Reset transform
                    // Optional: Yawn animation if elements exist
                    const $mouth = $sheep.find(".mouth");
                    if ($mouth.length) {
                        $mouth.addClass("yawn");
                        setTimeout(() => $mouth.removeClass("yawn"), 800);
                    }
                }
            });
        });

        // Spin interval - ensure elements exist
        const spinInterval = setInterval(function () {
            const $sheepForInterval = $(".sheep-wrapper .sheep");
            if ($sheepForInterval.length === 0) {
                // Optionally clear interval if sheep is removed from DOM
                // clearInterval(spinInterval);
                return;
            }

            // Check if sheep is currently being animated or hidden
            if ($sheepForInterval.is(':animated') || !$sheepWrapper.is(':visible')) {
                return;
            }

            $sheepForInterval.stop(true, true).css("transform", "rotate(0deg)");
            $({ deg: 0 }).animate({ deg: 360 }, {
                duration: 1000,
                easing: 'linear',
                step: function (now) {
                    $sheepForInterval.css("transform", "rotate(" + now + "deg)");
                },
                complete: function () {
                    $sheepForInterval.css("transform", "none");
                    const $mouthForInterval = $sheepForInterval.find(".mouth");
                    if ($mouthForInterval.length) {
                        $mouthForInterval.addClass("yawn");
                        setTimeout(() => $mouthForInterval.removeClass("yawn"), 1000);
                    }
                }
            });
        }, 10000); 

    } else {
        console.log("Sheep wrapper element not found. Sheep logic skipped.");
    }

}); // <-- End of the single $(document).ready()
