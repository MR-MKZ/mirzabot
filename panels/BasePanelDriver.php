<?php

abstract class BasePanelDriver
{
    const UNSUPPORTED = "\0panel.driver.unsupported\0";

    protected $manager;

    public function __construct(ManagePanel $manager)
    {
        $this->manager = $manager;
    }

    public function create(PanelContext $c)
    {
        return self::UNSUPPORTED;
    }

    public function read(PanelContext $c)
    {
        return self::UNSUPPORTED;
    }

    public function readBulk(PanelContext $c)
    {
        return self::UNSUPPORTED;
    }

    public function revokeSub(PanelContext $c)
    {
        return self::UNSUPPORTED;
    }

    public function remove(PanelContext $c)
    {
        return self::UNSUPPORTED;
    }

    public function modify(PanelContext $c)
    {
        return self::UNSUPPORTED;
    }

    public function changeStatus(PanelContext $c)
    {
        return self::UNSUPPORTED;
    }

    public function resetUsage(PanelContext $c)
    {
        return self::UNSUPPORTED;
    }

    public function extendService(PanelContext $c)
    {
        return self::UNSUPPORTED;
    }

    public function extraVolume(PanelContext $c)
    {
        return self::UNSUPPORTED;
    }

    public function extraTime(PanelContext $c)
    {
        return self::UNSUPPORTED;
    }

    public function setNewLimit(PanelContext $c)
    {
        return self::UNSUPPORTED;
    }

    public function countUser(PanelContext $c)
    {
        return self::UNSUPPORTED;
    }

    public function findByUuid(PanelContext $c)
    {
        return self::UNSUPPORTED;
    }

    protected function uuidMatch($value, array $uuids)
    {
        if (!is_string($value) || $value === '') {
            return false;
        }
        return in_array($value, $uuids, true) || in_array(strtolower($value), $uuids, true);
    }

    protected function DataUser($name_panel, $username)
    {
        return $this->manager->DataUser($name_panel, $username);
    }

    protected function Modifyuser($username, $name_panel, $config = array())
    {
        return $this->manager->Modifyuser($username, $name_panel, $config);
    }

    protected function ResetUserDataUsage($username, $name_panel)
    {
        return $this->manager->ResetUserDataUsage($username, $name_panel);
    }

    protected function applyModify($username, $name_panel, $data)
    {
        $result = $this->manager->Modifyuser($username, $name_panel, $data);
        if ($result['status'] == false) {
            return array(
                'status' => false,
                'msg' => $result['msg']
            );
        }
        return $result;
    }

    protected function absoluteSubUrl($url, $panel)
    {
        if (preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', (string) $url)) {
            return $url;
        }
        return $panel['url_panel'] . "/" . ltrim((string) $url, "/");
    }

    protected function invalidPanelResponse($response)
    {
        $status = $response['status'] ?? '?';
        $excerpt = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($response['body'] ?? '')))), 0, 150);
        return array(
            'status' => 'Unsuccessful',
            'msg' => "Panel returned an invalid (non-JSON) response (HTTP {$status}): {$excerpt}"
        );
    }
}
