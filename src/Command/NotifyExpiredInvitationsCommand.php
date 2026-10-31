<?php declare(strict_types=1);

namespace Monarc\BackOffice\Command;

use Monarc\BackOffice\Service\CampaignNotificationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class NotifyExpiredInvitationsCommand extends Command
{
    protected static $defaultName = 'monarc:notify-expired-invitations';

    public function __construct(private CampaignNotificationService $campaignNotificationService)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription(
            'Tells the campaign administrators which entities did not answer before the response deadline.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $campaignsCount = $this->campaignNotificationService->notifyExpiredInvitations();
        } catch (Throwable $e) {
            $output->writeln('[' . date('Y-m-d H:i:s') . '] An error occurred: ' . $e->getMessage());

            return 1;
        }

        $output->writeln('[' . date('Y-m-d H:i:s') . '] Campaigns with expired invitations: ' . $campaignsCount);

        return 0;
    }
}
