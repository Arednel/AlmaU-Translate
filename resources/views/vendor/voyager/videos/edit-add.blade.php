@extends('voyager::master')

@section('page_title', __('voyager::generic.' . (isset($dataTypeContent->id) ? 'edit' : 'add')) . ' ' .
    $dataType->getTranslatedAttribute('display_name_singular'))

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@stop

@section('page_header')
    <h1 class="page-title">
        <i class="{{ $dataType->icon }}"></i>
        {{ __('voyager::generic.' . (isset($dataTypeContent->id) ? 'edit' : 'add')) . ' ' . $dataType->getTranslatedAttribute('display_name_singular') }}
    </h1>
@stop

@section('content')
    <div class="page-content container-fluid">


        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <form class="form-edit-add" role="form"
                        action="@if (!is_null($dataTypeContent->getKey())) {{ route('voyager.' . $dataType->slug . '.update', $dataTypeContent->getKey()) }}@else{{ route('voyager.' . $dataType->slug . '.store') }} @endif"
                        method="POST" enctype="multipart/form-data" autocomplete="off">
                        <!-- PUT Method if we are editing -->
                        @if (isset($dataTypeContent->id))
                            {{ method_field('PUT') }}
                        @endif
                        {{ csrf_field() }}
                        {{-- <div class="panel"> --}}
                        @if (count($errors) > 0)
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="panel-body">
                            <div class="form-group">
                                <label for="video_url">Ссылка на видео</label>
                                <input type="text" class="form-control" id="video_url" name="video_url">
                            </div>

                            <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
                            <link rel="stylesheet" href="{{ asset('css/fileInput.css') }}">
                            <div class="form-group">
                                <label for="video_file">Видеофайл</label>
                                <div class="file-upload-wrapper" data-text="Выберите файл">
                                    <input name="video_file" type="file" class="file-upload-field"
                                        accept="video/*, .mkv">
                                </div>
                            </div>
                            <script>
                                $("form").on("change", ".file-upload-field", function() {
                                    $(this).parent(".file-upload-wrapper").attr("data-text", $(this).val().replace(/.*(\/|\\)/, ''));
                                });
                            </script>

                            <link rel="stylesheet" href="{{ asset('css/checkbox.css') }}">
                            <div class="form-group">
                                <label for="checkbox-1">Перевести аудио</label>

                                <div class="checkbox-wrapper">
                                    <input class="tgl tgl-light" id="checkbox-1" name="translate_audio" type="checkbox" />
                                    <label class="tgl-btn" for="checkbox-1">
                                </div>
                            </div>



                        </div>
                </div>


                <div class="panel-footer">
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
                </form>
            </div>
        </div>
    </div>


    <div style="display:none">
        <input type="hidden" id="upload_url" value="{{ route('voyager.upload') }}">
        <input type="hidden" id="upload_type_slug" value="{{ $dataType->slug }}">
    </div>
    </div>
@stop

@section('javascript')
    <script>
        $('document').ready(function() {
            $('.toggleswitch').bootstrapToggle();
        });
    </script>
@stop
