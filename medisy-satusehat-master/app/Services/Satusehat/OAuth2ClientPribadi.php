<?php

namespace App\Services\Satusehat;

use App\Models\User;
use Carbon\Carbon;
use Dotenv\Dotenv;
use Exception;
use GuzzleHttp\Client;
// Guzzle HTTP Package
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;
// SATUSEHAT Model & Log
use Satusehat\Integration\Models\SatusehatLog;
use Satusehat\Integration\Models\SatusehatToken;

class OAuth2ClientPribadi
{
    public string $auth_url, $base_url;
    public function __construct()
    {
        $dotenv = Dotenv::createUnsafeImmutable(getcwd());
        $dotenv->safeLoad();

        if (getenv('SATUSEHAT_ENV') == 'PROD') {
            $this->auth_url = getenv('SATUSEHAT_AUTH_PROD', 'https://api-satusehat.kemkes.go.id/oauth2/v1');
            $this->base_url = getenv('SATUSEHAT_FHIR_PROD', 'https://api-satusehat.kemkes.go.id/fhir-r4/v1');
        } elseif (getenv('SATUSEHAT_ENV') == 'STG') {
            $this->auth_url = getenv('SATUSEHAT_AUTH_STG', 'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1');
            $this->base_url = getenv('SATUSEHAT_FHIR_STG', 'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1');
        } elseif (getenv('SATUSEHAT_ENV') == 'DEV') {
            $this->auth_url = getenv('SATUSEHAT_AUTH_DEV', 'https://api-satusehat-dev.dto.kemkes.go.id/oauth2/v1');
            $this->base_url = getenv('SATUSEHAT_FHIR_DEV', 'https://api-satusehat-dev.dto.kemkes.go.id/fhir-r4/v1');
        }
    }

    public function token($id_user)
    {

        $User = User::where('id', $id_user)->first();
        if (!empty($User->token_created_at)) {
            $tokenCreatedAt = Carbon::parse($User->token_created_at);
            $currentDateTime = Carbon::now();
            $timeDifferenceInMinutes = $currentDateTime->diffInMinutes($tokenCreatedAt);
            if ($timeDifferenceInMinutes < 50) {
                return $User->token;
            }
        }

        $client = new Client();
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
        $options = [
            'form_params' => [
                'client_id' => $User->client_id,
                'client_secret' => $User->client_secret,
            ],
        ];

        $url = $this->auth_url . '/accesstoken?grant_type=client_credentials';


        $request = new Request('POST', $url, $headers);
        try {
            $res = $client->sendAsync($request, $options)->wait();
            $contents = json_decode($res->getBody()->getContents());

            if (isset($contents->access_token)) {

                Log::info('token_active', [
                    'user_id' => $User->id,
                    'token_active' => $contents->access_token,
                ]);

                // $User->update([
                //     'token_active' => $contents->access_token,
                // ]);

                User::where('id', $User->id)->update([
                    'token_active' => $contents->access_token
                ]);

                return $contents->access_token;
            } else {
                throw new Exception("Fail to get the access token");
            }
        } catch (ClientException $e) {
            // error.
            $res = json_decode($e->getResponse()->getBody()->getContents());
            $issue_information = $res->issue[0]->details->text;

            throw new Exception($issue_information);
        }
    }
}
