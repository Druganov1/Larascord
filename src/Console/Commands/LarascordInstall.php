<?php

namespace Jakyeru\Larascord\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use \GuzzleHttp;

class LarascordInstall extends Command
{
    protected $name = 'larascord:install';

    protected $description = 'Use this command to install Larascord.';

    protected $availableScops = [
        'activities.read',
        'activities.write',
        'applications.builds.read',
        'applications.builds.upload',
        'applications.commands',
        'applications.commands.update',
        'applications.entitlements',
        'applications.store.update',
        'bot',
        'connections',
        'email',
        'gdm.join',
        'guilds.join',
        'identify',
        'messages.read',
        'relationships.read',
        'rpc',
        'rpc.activities.write',
        'rpc.notifications.read',
        'rpc.voice.read',
        'rpc.voice.write',
        'webhook.incoming'
    ];

    protected string $baseUrl = 'https://discord.com/api';

    private string|null $clientId;
    private string|null $clientSecret;
    private string|null $token;
    private string|null $redirectUri;
    private string|null $scopes;

    /**
     * Handle the command.
     * @throws \Exception
     */
    public function handle()
    {
        // Getting the user's input
        $this->clientId = $this->ask('What is your Discord application\'s client id?');

        $this->info('');

        $this->clientSecret = $this->ask('What is your Discord application\'s client secret?');

        $this->info('');

        $this->token = $this->ask('What is your Discord bot\'s token?');

        $this->info('');

        $this->redirectUri = $this->ask('What is your Discord application\'s redirect uri?');

        $this->info('');

        $this->scopes = $this->ask('What scopes do you want to use? (separated by a ampersand (&))', 'identify&email');

        $this->info('');
        
        // Validating the user's input
        try {
            $this->validateInput();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return;
        }

        // Appending the secrets to the .env file
        $this->appendToEnvFile();

        $this->info('Larascord installed successfully!');

        if ($this->confirm('Do you want to validate the provided data through Discord API?', false)) {
            try {
                $this->validateDiscordApi();
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                return;
            }
        } else {
            $this->info('You can validate the provided data through Discord API later by running the command:');
            $this->info('php artisan larascord:validate');
        }
    }

    /**
     * Validate the user's input.
     * @throws \Exception
     */
    protected function validateInput()
    {
        $rules = [
            'clientId' => ['required', 'numeric'],
            'clientSecret' => ['required', 'string'],
            'token' => ['required', 'string'],
            'redirectUri' => ['required', 'url'],
            'scopes' => ['required', 'array'],
        ];

        $validator = Validator::make([
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'token' => $this->token,
            'redirectUri' => $this->redirectUri,
            'scopes' => explode('&', $this->scopes),
        ], $rules);

        $validator->validate();

        // make sure scopes exists in the available scopes
        foreach ($validator->validated()['scopes'] as $scope) {
            if (!in_array($scope, $this->availableScops)) {
                throw new \Exception('The scope '.$scope.' is not available. Available scopes: ' . implode(', ', $this->availableScops) . '.');
            }
        }
    }

    /**
     * Append the secrets to the .env file.
     */
    protected function appendToEnvFile()
    {

        (new Filesystem())->append('.env',PHP_EOL);

        (new Filesystem())->append('.env',PHP_EOL);
        (new Filesystem())->append('.env','DISCORD_CLIENT_ID='.$this->clientId);

        (new Filesystem())->append('.env',PHP_EOL);
        (new Filesystem())->append('.env','DISCORD_CLIENT_SECRET='.$this->clientSecret);

        (new Filesystem())->append('.env',PHP_EOL);
        (new Filesystem())->append('.env','DISCORD_GRANT_TYPE=authorization_code');

        (new Filesystem())->append('.env',PHP_EOL);
        (new Filesystem())->append('.env','DISCORD_BOT_TOKEN='.$this->token);

        (new Filesystem())->append('.env',PHP_EOL);
        (new Filesystem())->append('.env','DISCORD_REDIRECT_URI='.$this->redirectUri);

        (new Filesystem())->append('.env',PHP_EOL);
        (new Filesystem())->append('.env','DISCORD_SCOPE='.$this->scopes);
    }

    /**
     * Validate the provided data through Discord API.
     * @throws \Exception|GuzzleHttp\Exception\GuzzleException
     */
    protected function validateDiscordApi() {

        $this->info('Validating the data through Discord API...');

        $client = new GuzzleHttp\Client();

        try {
            $response = $client->get($this->baseUrl.'/users/@me', [
                'headers' => [
                    'Authorization' => 'Bot '.$this->token,
                ],
            ]);

            $response = json_decode($response->getBody()->getContents());

            if ($response->id !== $this->clientId) {
                throw new \Exception('The provided client belongs to another application.');
            }

            $this->info('The provided data is valid!');
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->error('The provided data is not valid.');
            return;
        }
    }
}