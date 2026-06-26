@extends('layouts.user')

@section('title', 'Quản lý thành viên bảng ' . $board->name)

@section('content')
    <div class="container-fluid mt-4 mb-5"> {{-- Use container-fluid for more space if needed --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3 mb-0 text-gray-800 text-white">
                    <span class="font-weight-bold">{{ $board->name }}</span>
                </h1>
            </div>
        </div>

        <hr class="my-4">

        <div class="row">
            {{-- Left Column: Invite & Pending Invitations --}}
            <div class="col-lg-5 mb-4 mb-lg-0">
                {{-- Invite Members Form --}}
                @if(Auth::user()->id == $board->user_id || Auth::user()->hasBoardPermission($board, 'board_member_manager'))
                    <x-common.card title="Mời thành viên mới" icon="fas fa-user-plus" class="shadow mb-4">
                            @if(session('success_invite'))
                            <x-common.alert type="success" class="small p-2">{{ session('success_invite') }}</x-common.alert> @endif
                            @if(session('error_invite'))
                            <x-common.alert type="danger" class="small p-2">{{ session('error_invite') }}</x-common.alert> @endif
                            @if(session('warning_invite'))
                            <x-common.alert type="warning" class="small p-2">{{ session('warning_invite') }}</x-common.alert> @endif

                            <x-common.form :action="route('boards.invite', $board)" method="POST">
                                <x-common.form-field label="Email thành viên" name="email">
                                    <x-common.text-input type="email" name="email" id="email" groupClass=""
                                        class="form-control-sm @error('email') is-invalid @enderror"
                                        :value="old('email')" required placeholder="nhap@emailcuaban.com" />
                                </x-common.form-field>
                                <x-common.form-field label="Vai trò (Quyền)" name="role_permission_name">
                                    <x-common.select name="role_permission_name" id="role_permission_name" required
                                        class="form-control-sm @error('role_permission_name') is-invalid @enderror"
                                        placeholder="-- Chọn vai trò --"
                                        :options="$potentialRoles" :selected="old('role_permission_name')" />
                                </x-common.form-field>
                                <x-common.button variant="outline-dark" icon="fas fa-paper-plane" class="btn-sm btn-block">
                                    Gửi lời mời
                                </x-common.button>
                            </x-common.form>
                    </x-common.card>
                @endif

                {{-- Pending Invitations --}}
                @if(Auth::user()->id == $board->user_id || Auth::user()->hasBoardPermission($board, 'board_member_manager'))
                    <x-common.card class="shadow" bodyClass="card-body p-0">
                        <x-slot:header>
                            <h6 class="m-0 font-weight-bold text-white">
                                <i class="fas fa-hourglass-half mr-2"></i>Lời mời đang chờ
                                (@if($pendingInvitations) <span
                                    class="badge badge-warning badge-pill">{{ $pendingInvitations->count() }}</span> @else 0
                                @endif)
                            </h6>
                        </x-slot:header>
                        {{-- p-0 để list-group sát mép card --}}
                            @if($pendingInvitations && $pendingInvitations->count() > 0)
                                <ul class="list-group list-group-flush">
                                    @foreach($pendingInvitations as $invitation)
                                        <li class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span class="font-weight-bold">{{ $invitation->email }}</span>
                                                    <em class="small text-muted d-block">
                                                        Vai trò:
                                                        {{ $potentialRoles[$invitation->role_permission_name] ?? $invitation->role_permission_name }}
                                                    </em>
                                                    <small class="text-muted d-block" style="font-size: 0.75rem;">
                                                        Mời bởi: {{ $invitation->inviter->name }}
                                                        ({{ $invitation->created_at->diffForHumans() }})
                                                    </small>
                                                </div>
                                                <form action="{{ route('boards.invitations.cancel', [$board, $invitation]) }}"
                                                    method="POST" onsubmit="return confirm('Hủy lời mời này?');" class="ml-2">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Hủy lời mời">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="p-3 text-center text-muted small">
                                    <i class="fas fa-info-circle mr-1"></i> Không có lời mời nào đang chờ.
                                </div>
                            @endif
                    </x-common.card>
                @endif
            </div>

            {{-- Right Column: Current Members --}}
            <div class="col-lg-7">
                <x-common.card title="Thành viên hiện tại" icon="fas fa-users" class="shadow">
                        @if(session('success_member'))
                        <x-common.alert type="success" class="small p-2">{{ session('success_member') }}</x-common.alert> @endif
                        @if(session('error_member'))
                        <x-common.alert type="danger" class="small p-2">{{ session('error_member') }}</x-common.alert> @endif

                        <div class="table-responsive">
                            <table class="table table-hover table-sm" style="font-size: 0.9rem;">
                                <thead>
                                    <tr>
                                        <th>Thành viên</th>
                                        <th>Email</th>
                                        <th style="width: 30%;">Vai trò</th>
                                        @if(Auth::id() === $board->user_id || Auth::user()->hasBoardPermission($board, 'board_member_manager'))
                                            <th class="text-right" style="width: 10%;">Hành động</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- Owner --}}
                                    <tr>
                                        <td>
                                            <img src="https://i.pravatar.cc/30?u={{ urlencode($board->owner->email) }}"
                                                class="rounded-circle mr-2" width="24" height="24" alt="">
                                            <strong>{{ $board->owner->name }}</strong>
                                        </td>
                                        <td>{{ $board->owner->email }}</td>
                                        <td><span class="badge badge-success px-2 py-1">Chủ sở hữu</span></td>
                                        @if(Auth::id() === $board->user_id || Auth::user()->hasBoardPermission($board, 'board_member_manager'))
                                            <td class="text-right"></td> {{-- Placeholder for alignment --}}
                                        @endif
                                    </tr>
                                    {{-- Other Members --}}
                                    @forelse($membersData as $memberItem)
                                        @php $memberUser = $memberItem['user']; @endphp
                                        <tr data-member-id="{{ $memberUser->id }}">
                                            <td>
                                                <img src="https://i.pravatar.cc/30?u={{ urlencode($memberUser->email) }}"
                                                    class="rounded-circle mr-2" width="24" height="24" alt="">
                                                {{ $memberUser->name }}
                                            </td>
                                            <td>{{ $memberUser->email }}</td>
                                            <td>
                                                @php
                                                    $currentHighestRole = null;
                                                    foreach (array_keys($potentialRoles) as $roleKey) {
                                                        if (in_array($roleKey, $memberItem['roles'])) {
                                                            $currentHighestRole = $roleKey;
                                                            break;
                                                        }
                                                    }
                                                @endphp
                                                <x-common.select class="form-control-sm member-role-select"
                                                    :options="$potentialRoles" :selected="$currentHighestRole"
                                                    @disabled(Auth::id() !== $board->user_id && !Auth::user()->hasBoardPermission($board, 'board_member_manager')) />
                                            </td>
                                            @if(Auth::id() === $board->user_id || Auth::user()->hasBoardPermission($board, 'board_member_manager'))
                                                <td class="text-right">
                                                    <button class="btn btn-outline-danger btn-sm remove-member-btn"
                                                        title="Xóa thành viên">
                                                        <i class="fas fa-user-times"></i>
                                                    </button>
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        @if(Auth::id() === $board->user_id || Auth::user()->hasBoardPermission($board, 'board_member_manager'))
                                            <tr>
                                                <td colspan="4" class="text-center text-muted pt-3 pb-3"><i
                                                        class="fas fa-info-circle mr-1"></i> Chưa có thành viên nào (ngoài chủ sở
                                                    hữu).</td>
                                            </tr>
                                        @else
                                            <tr>
                                                <td colspan="3" class="text-center text-muted pt-3 pb-3"><i
                                                        class="fas fa-info-circle mr-1"></i> Chưa có thành viên nào (ngoài chủ sở
                                                    hữu).</td>
                                            </tr>
                                        @endif
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                </x-common.card>
            </div>
        </div>
    </div>
@endsection