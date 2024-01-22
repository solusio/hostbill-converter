<?php

class BaseCommand
{
    protected FileService $fileService;
    protected DatabaseService $db;
    protected ApiV2Service $v2Api;

    public function __construct()
    {
        $this->fileService = new FileService();
        $this->db = new DatabaseService();
        $this->v2Api = new ApiV2Service($this->db);
        $this->v2Api->init();
    }
}