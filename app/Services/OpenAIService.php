<?php

namespace App\Services;

use OpenAI;

class OpenAIService
{
    public function client()
    {
        return OpenAI::client(config('services.openai.api_key'));
    }
}
