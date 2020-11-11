<?php declare(strict_types=1);
namespace Naisdevice\Jita\Session;

class User {
    private string $objectId;
    private string $email;
    private string $name;

    /** @var array<string> */
    private array $groups;

    /**
     * Class constructor
     *
     * @param string $objectId
     * @param string $email
     * @param string $name
     * @param array<string> $groups
     */
    public function __construct(string $objectId, string $email, string $name, array $groups) {
        $this->objectId = $objectId;
        $this->email    = $email;
        $this->name     = $name;
        $this->groups   = $groups;
    }

    /**
     * Get the object ID
     *
     * @return string
     */
    public function getObjectId() : string {
        return $this->objectId;
    }

    /**
     * Get the email
     *
     * @return string
     */
    public function getEmail() : string {
        return $this->email;
    }

    /**
     * Get the name property
     *
     * @return string
     */
    public function getName() : string {
        return $this->name;
    }

    /**
     * Get all groups
     *
     * @return array<string>
     */
    public function getGroups() : array {
        return $this->groups;
    }
}
