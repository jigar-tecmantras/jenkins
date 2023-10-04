<?php

namespace App\Thread;

class Thread
{
    private $threadId;
    private $title;
    private $content;

    public function __construct($threadId, $title, $content)
    {
        $this->threadId = $threadId;
        $this->title = $title;
        $this->content = $content;
    }

    public function getThreadId()
    {
        return $this->threadId;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setContent($content)
    {
        $this->content = $content;
    }
}
