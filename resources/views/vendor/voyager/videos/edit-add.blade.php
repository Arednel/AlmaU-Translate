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
                                <input type="text" class="form-control" id="video_url" name="video_url"
                                    placeholder="Ссылка на видео" value="">
                            </div>

                            <div class="form-group">
                                <label for="video_file">Видеофайл</label>
                                <input type="file" data-name="video_file" name="video_file">
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
