<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Capability;

use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\Contracts\IbexaBlocks\Subscribers;

/** Personal data (names and e-mail addresses): an administrator only, and it is the one that says so on the card. */
final class NewsletterSubscribersCapability extends AbstractCapability
{
    public function __construct(private readonly ?Subscribers $subscribers = null)
    {
    }

    public function name(): string
    {
        return 'newsletter.subscribers.list';
    }

    public function summary(): string
    {
        return 'How many people subscribed to the newsletter, and the latest ones.';
    }

    public function description(): string
    {
        return 'Counts the subscribers and lists the latest, with their list (the newsletter of a site, or of the sites that share it), e-mail address, name, whether they confirmed and whether they are active. Personal data: only for an administrator, and not to be repeated beyond what is asked.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'description' => 'How many of the latest (default 20)'], 'list' => ['type' => 'string', 'description' => 'Only the subscribers of this list (the newsletter.list_id of a site; each row says its list). Default: all the lists']]];
    }

    public function risk(): Risk
    {
        return Risk::ACCESS;
    }

    public function requires(): array
    {
        return [['setup', 'administrate']];
    }

    public function examples(): array
    {
        return [['description' => 'The latest subscribers', 'arguments' => ['limit' => 10]]];
    }

    public function validate(array $arguments): ?string
    {
        return $this->subscribers === null ? 'The newsletter is not turned on for this project.' : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Read the newsletter subscribers', [['Latest', (string) (int) ($arguments['limit'] ?? 20)], ['List', (string) ($arguments['list'] ?? 'all')]], ['The e-mail addresses and names of the subscribers are shown: personal data.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $subscribers = $this->subscribers ?? throw new \LogicException('validate() refuses a project without a newsletter.');

        $list = isset($arguments['list']) && $arguments['list'] !== '' ? (string) $arguments['list'] : null;

        return CapabilityResult::ok(sprintf('%d subscriber(s)%s.', $subscribers->count($list), $list !== null ? ' in the list ' . $list : ''), ['count' => $subscribers->count($list), 'latest' => $subscribers->latest((int) ($arguments['limit'] ?? 20), $list)]);
    }
}
