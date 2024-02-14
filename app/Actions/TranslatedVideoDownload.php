<?php

namespace App\Actions;

use TCG\Voyager\Actions\AbstractAction;

class TranslatedVideoDownload extends AbstractAction
{
    public function getTitle()
    {
        return 'Скачать видео';
    }

    public function getIcon()
    {
        return 'voyager-download';
    }

    public function getPolicy()
    {
        return 'read';
    }

    public function getAttributes()
    {
        return [
            'class' => 'btn btn-sm btn-primary pull-right',
            'style' => 'margin-right:5px;'
        ];
    }

    public function getDefaultRoute()
    {
        return route('translated_download', ['id' => $this->data->id]);
    }

    public function shouldActionDisplayOnDataType()
    {
        return $this->dataType->slug == 'videos';
    }
}
