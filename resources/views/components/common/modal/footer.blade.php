{{--
    Phần chân (footer) của modal. Mọi class bổ sung sẽ gộp vào div.modal-footer.
--}}
<div {{ $attributes->merge(['class' => 'modal-footer']) }}>
    {{ $slot }}
</div>
