<?php declare(strict_types=1);
namespace Naisdevice\Jita;

class FlashMessage {
    private string $message;
    private bool $isError = false;

    /**
     * Class constructor
     *
     * @param string $message
     * @param bool $isError
     */
    public function __construct(string $message, bool $isError = false) {
        $this->message = $message;
        $this->isError = $isError;
    }

    public function isError() : bool {
        return $this->isError;
    }

    public function getMessage() : string {
        return $this->message;
    }
}
