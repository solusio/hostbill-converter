<?php

class ConvertAccountsCommand extends BaseCommand
{
    public function handle(): void
    {
        $servers = $this->fileService->load(FileService::SERVERS);
        $importedServers = $this->fileService->load(FileService::IMPORTED_SERVERS);
        $v1ProductsMap = $this->fileService->load(FileService::PACKAGES);

        $v1Module = $this->db->getModule(DatabaseService::SolusVMv1);
        $v1Products = $this->db->getProducts($v1Module);

        try {
            $initialAccounts = $this->fileService->load(FileService::HOSTBILL_ACCOUNTS);
        } catch (\Exception $e) {
            $initialAccounts = [];
        }

        $totalAccounts = 0;
        $convertedAccounts = 0;

        foreach ($v1Products as $product) {
            $accounts = $this->db->getAccounts((int)$product['product_id']);
            $totalAccounts += count($accounts);

            // Update virtual servers (Hostbill accounts)
            foreach ($accounts as $account) {
                if (!isset($initialAccounts[$account['id']])) {
                    $initialAccounts[$account['id']] = $account;
                }

                $productId = $v1ProductsMap[$product['product_id']] ?? 0;

                if (!$productId) {
                    echo "Not found duplicated product for Product ID: " . $product['product_id'] . "\n";
                    continue;
                }

                $data = unserialize($account['extra_details']);
                $v1serverId = (int)$data['option6'];

                if (!isset($importedServers[$v1serverId])) {
                    echo "Not found imported server for " . $account['domain'] . "\n";
                }


                $v2serverId = (int)$importedServers[$v1serverId];
                if (!isset($servers[$v2serverId])) {
                    echo "There is no server with id $v2serverId for " . $account['domain'] . "\n";
                    continue;
                }
                if ($servers[$v2serverId]['name'] !== $account['domain']) {
                    echo "Founded domain " . $servers[$v2serverId]['name'] . "is not equal with expected " . $account['domain'] . "\n";
                    continue;
                }

                $data = [
                    'option6' => $v2serverId,
                    'userid' => $servers[$v2serverId]['user_id'],
                ];

                $this->db->updateAccount($account['id'], $this->v2Api->serverId, serialize($data), $productId);

                $convertedAccounts++;
            }
        }

        $this->fileService->save(FileService::HOSTBILL_ACCOUNTS, $initialAccounts);

        echo "Total number of accounts: $totalAccounts\n";
        echo "Number of updated accounts: $convertedAccounts\n";
    }
}