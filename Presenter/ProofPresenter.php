<?php
require_once __DIR__ . '/../Model/ProofModel.php';

class ProofPresenter
{
    private $model;

    public function __construct()
    {
        $this->model = new ProofModel();
    }

    public function getProofDetails(int $id): ?array
    {
        return $this->model->getProofDetails($id);
    }
}
