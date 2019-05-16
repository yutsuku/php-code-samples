<?php

namespace CloakHosting\App\Extensions\Notifier;

use CloakHosting\App\Extensions\Notifier\Exceptions\NotifierConfigurationMissingException;
use CloakHosting\App\Extensions\Notifier\Exceptions\NotifierInvalidStatusException;

/**
 * Class SlackNotifierDriver
 * Barebones notifications for Slack
 * @package CloakHosting\App\Extensions\Notifier
 */
class SlackNotifierDriver implements NotifierDriverInterface
{    
    /** @var string */
    private $webhook;
    
    /**
     * attachments colors
     */
    const COLOR_GOOD = 'good';
    const COLOR_WARNING = 'warning';
    const COLOR_DANGER = 'danger';
    const COLOR_INFO = '#ddd';
    
    protected static $statuses = array(
        Notifier::SUCCESS => self::COLOR_GOOD,
        Notifier::INFO    => self::COLOR_INFO,
        Notifier::WARNING => self::COLOR_WARNING,
        Notifier::ALERT   => self::COLOR_DANGER,
    );
    
    /**
     * @param uri
     */
    public function setWebHook($uri)
    {
        $this->webhook = $uri;
    }
    
    /**
     * @return string
     */
    public function getWebHook()
    {
        return $this->webhook;
    }
    
    public function message($webhook, $message, $severity)
    {
        $postfields = json_encode(
            array(
                'attachments' => array(
                    array(
                        'fallback' => $message,
                        'text' => $message,
                        'color' => $severity,
                    ),
                ),
            )
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhook);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200)
        {
            return true;
        }
        
        return false;
    }

    public function __construct(array $config)
    {
        $required = array("webhook");

        if ($missing = array_diff($required, array_keys($config))) {
            throw new NotifierConfigurationMissingException($missing);
        }
        
        $this->setWebHook($config['webhook']);
    }

    /**
     * {@inheritdoc}
     */
    public function notify($message, $severity)
    {
        return $this->message($this->getWebHook(), $message, $severity);
    }

    /**
     * {@inheritdoc}
     */
    public function translateSeverity($name)
    {
        if (!array_key_exists($name, self::$statuses)) {
            throw new NotifierInvalidStatusException;
        }

        return self::$statuses[$name];
    }
}
