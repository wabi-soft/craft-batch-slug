<?php
namespace wabisoft\craftbatchslug\variables;

use wabisoft\craftbatchslug\services\Remap;

class BatchSlugHelper
{
    public function preview()
    {
        return (new Remap)->preview();
    }
}
