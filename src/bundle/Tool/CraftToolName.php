<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\Tool;

/**
 * Names of the internal tools of this package (the model does not call them: the capabilities and the site explorer do).
 */
final class CraftToolName
{
    public const BROWSE_SITE_STRUCTURE = 'browse_site_structure';
    public const FIND_PARENT_CANDIDATES = 'find_parent_candidates';
    public const LIST_BLOCKS = 'list_blocks';
    public const CREATE_SITE_STRUCTURE = 'create_site_structure';
}
