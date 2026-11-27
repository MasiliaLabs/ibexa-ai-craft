<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Support;

/**
 * Where the conventions come from, asked each time they are needed: the conventions of a site are those of the site of the
 * request (a worker serves several), so a class that needs them never keeps the result.
 */
interface ConventionsSource
{
    public function conventions(): Conventions;
}
