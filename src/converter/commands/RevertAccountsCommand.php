<?php

class RevertAccountsCommand extends BaseCommand
{
    public function handle(array $ids): void
    {
        $initialAccounts = $this->fileService->load(FileService::HOSTBILL_ACCOUNTS);

        foreach ($ids as $id) {
            $account = $initialAccounts[$id] ?? 0;

            if ($account === 0) {
                echo "Not found initial account data for account ID: " . $id . "\n";
                continue;
            }

            $this->db->updateAccount($id, $account['server_id'], $account['extra_details'], $account['product_id']);
        }

        echo "Done!";
    }
}