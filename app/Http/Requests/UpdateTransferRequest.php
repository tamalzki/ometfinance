<?php

namespace App\Http\Requests;

/**
 * Same validation + sanitization as {@see StoreTransferRequest}; used when
 * editing an existing transfer so authorization and rules stay in one place.
 */
class UpdateTransferRequest extends StoreTransferRequest
{
}
