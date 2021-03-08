<!doctype html>
<html lang="vi,en">
<head>
    @include('layout.header')
    @yield('header')
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed text-sm">
<div class="wrapper">
    <!-- Navbar -->
    @include('layout.navbar_menu')
    <!-- /.navbar -->
    <!-- Main Sidebar Container -->
    @include('layout.left_menu')
    <!-- Content Wrapper. Contains page content -->
    {{--begin content body--}}
    @yield('content')
    {{--end content body--}}
    <!-- Control Sidebar -->
    @include('layout.control_sidebar')
</div>
<div class="modal fade" id="modal_upload">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
{{--            @include('gallerypage.modal')--}}
        </div>
    </div>
</div>
<!-- /.modal -->
<!-- /.content-wrapper -->
<footer class="main-footer">
    <strong>Copyright &copy; 2014-2019 <a target="_blank" href="https://remixwebsite.com">phát triển bởi remixwebsite.com</a>.</strong>
    Đã đăng ký bản quyển
    <div class="float-right d-none d-sm-inline-block">
        <b>Version</b> 0.0.0.beta
    </div>
</footer>
<!-- Control Sidebar -->
<aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
</aside>
<!-- /.control-sidebar -->
{{--begin script footer--}}
{{--<div class="storage" id="modal_storage">--}}
    {{--<button type="button" class="close" id="btn_close_modal_storage">--}}
        {{--<span aria-hidden="true">&times;</span>--}}
    {{--</button>--}}
    {{--<p>Limit Storage: </p>--}}
    {{--<input id="storage" type="text" name="storage" value="0">--}}
{{--</div>--}}
{{--<button type="button" class="btn btn-default btn-storage" id="button_storage">--}}
    {{--<i class="fas fa-database"></i>--}}
{{--</button>--}}
@include('layout.footer')
{{--<script async src="{{admin_assets('js/storage.js')}}"></script>--}}
@yield('script')
{{--end script footer--}}
</body>
</html>
