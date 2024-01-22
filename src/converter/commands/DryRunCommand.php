<?php

class DryRunCommand extends BaseCommand
{
    public function handle(): void
    {
        $v1Module = $this->db->getModule(DatabaseService::SolusVMv1);
        $v1Products = $this->db->getProducts($v1Module);
        $importedServers = $this->fileService->load(FileService::IMPORTED_SERVERS);

        $totalServers = 0;
        $accountsToConvert = [];

        foreach ($v1Products as $product) {
            $accounts = $this->db->getAccounts((int)$product['product_id']);
            $totalServers += count($accounts);

            foreach ($accounts as $account) {
                $data = unserialize($account['extra_details']);

                $v1serverId = (int)$data['option6'];
                if (isset($importedServers[$v1serverId])) {
                    if (count($accountsToConvert) === 0) {
                        echo "ID | hostname\n";
                    }

                    $accountsToConvert[] = $account['id'];
                    echo $account['id'] . ' | ' . $account['domain'] . "\n";
                }
            }
        }

        echo "Total number of servers: $totalServers. Number of servers to convert: " . count($accountsToConvert) . ".\n";

        echo "Revert command: php index.php revert-accounts --accounts=" . implode(',', $accountsToConvert);
    }
}