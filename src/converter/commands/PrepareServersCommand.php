<?php

class PrepareServersCommand extends BaseCommand
{
    public function handle(int $clusterImport): void
    {
        if (!$clusterImport) {
            $clusterImports = $this->v2Api->getClusterImports();
            if (count($clusterImports) === 1) {
                $clusterImport = $clusterImports[0]['id'];
            } else {
                throw new Exception("Can't find cluster import! Pass it using --cluster-import argument");
            }
        }

        $this->fileService->save(
            FileService::IMPORTED_SERVERS,
            $this->v2Api->getClusterImportEntities($clusterImport, 'servers'),
        );

        $this->fileService->save(FileService::SERVERS, $this->v2Api->getSolusVm2Servers());
        echo "Done!";
    }
}