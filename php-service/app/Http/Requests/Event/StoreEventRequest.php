<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
            'event_date' => ['required', 'date', 'after:now'],
            'ticket_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'total_tickets' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
