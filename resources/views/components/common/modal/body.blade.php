{{--
    Phần thân (body) của modal. Mọi class bổ sung sẽ gộp vào div.modal-body.
--}}
<div {{ $attributes->merge(['class' => 'modal-body']) }}>
    {{ $slot }}
</div>
