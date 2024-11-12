<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'rsa:fetch-sf-record',
    description: 'Add a short description for your command',
)]
class RsaFetchSfRecordCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('sfid', InputArgument::OPTIONAL, 'Argument description')//            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sfId = $input->getArgument('sfid');

        if (!$sfId) {
            $io->error('You must pass a salesforce id to look for');
            return Command::FAILURE;
        }

//        if ($input->getOption('option1')) {
//            // ...
//        }

        // FETCH TOKEN
        $curl = curl_init();
        $url = sprintf(
            'https://%s/services/oauth2/token?grant_type=password&client_id=%s&client_secret=%s&username=%s&password=%s',
            $_ENV["SF_AUTH_ENDPOINT"],
            $_ENV["SF_AUTH_CLIENT_ID"],
            $_ENV["SF_AUTH_CLIENT_SECRET"],
            urlencode($_ENV["SF_AUTH_USER"]),
            $_ENV["SF_AUTH_PASS"],
        );
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Cookie: BrowserId=G-R9nmoDEe-bFe1i6Wt1hQ; CookieConsentPolicy=0:1; LSKey-c$CookieConsentPolicy=0:1'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $response_payload = json_decode($response, TRUE);
        $token = $response_payload["access_token"];

        if (!$token) {
            $io->error('Error connecting to RSA');
            $io->info($response);
            return Command::FAILURE;
        }

        $io->info("Token acquired");

        // FETCH RECORD
        $url = sprintf(
            'https://%s/services/data/v56.0/sobjects/Contact/%s',
            $_ENV['SF_AUTH_ENDPOINT'],
            $sfId,
        );
        $io->info(sprintf("Fetching record %s", $url));
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                sprintf('Authorization: Bearer %s', $token),
                'Cookie: BrowserId=G-R9nmoDEe-bFe1i6Wt1hQ; CookieConsentPolicy=0:1; LSKey-c$CookieConsentPolicy=0:1'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $json = json_decode($response, true);
        file_put_contents("sf_record_" . $sfId . ".json", json_encode($response, JSON_PRETTY_PRINT));
        $io->info("Mentor access set to: " . $json["Mentor_Access__c"]);

        return Command::SUCCESS;
    }
}
