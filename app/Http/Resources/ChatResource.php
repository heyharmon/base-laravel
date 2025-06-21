<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'function_name' => $this->function_name,
            'function_arguments' => $this->function_arguments,
            'function_response' => $this->function_response,
            'reasoning' => $this->reasoning,
            'web_search_results' => $this->web_search_results,
            'job_id' => $this->job_id,
            'job_status' => $this->job_status,
            'created_at' => $this->created_at,
        ];
    }
}
