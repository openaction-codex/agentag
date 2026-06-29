<?php

namespace App\AgentTag\Agent;

interface AgentProfileProvider
{
    public function profile(): AgentProfile;
}
