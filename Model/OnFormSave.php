<?php

namespace Kimerikal\UtilBundle\Model;

interface OnFormSave
{
    public function beforeFromSave();

    public function afterFormSave();

    public function persistAgainAfterFormSave(): bool;
}
