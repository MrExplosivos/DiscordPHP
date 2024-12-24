<?php

/*
 * This file is a part of the DiscordPHP project.
 *
 * Copyright (c) 2015-present David Cole <david.cole1340@gmail.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

namespace Discord\Parts\Interactions\Request;

use Discord\Helpers\CollectionInterface;
use Discord\Parts\Channel\Attachment;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Guild\Role;
use Discord\Parts\Part;
use Discord\Parts\Thread\Thread;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;

/**
 * Represents the data associated with an interaction.
 *
 * @link https://discord.com/developers/docs/interactions/receiving-and-responding#interaction-object-resolved-data-structure
 *
 * @since 7.0.0
 *
 * @property CollectionInterface|User[]|null             $users       The ids and User objects.
 * @property CollectionInterface|Member[]|null           $members     The ids and partial Member objects.
 * @property CollectionInterface|Role[]|null             $roles       The ids and Role objects.
 * @property CollectionInterface|Channel[]|Thread[]|null $channels    The ids and partial Channel objects.
 * @property CollectionInterface|Message[]|null          $messages    The ids and partial Message objects.
 * @property CollectionInterface|Attachment[]|null       $attachments The ids and partial Attachment objects.
 *
 * @property string|null $guild_id ID of the guild internally passed from Interaction.
 */
class Resolved extends Part
{
    /**
     * {@inheritDoc}
     */
    protected $fillable = [
        'users',
        'members',
        'roles',
        'channels',
        'messages',
        'attachments',

        // @internal
        'guild_id',
    ];

    /**
     * {@inheritDoc}
     */
    protected $hidden = ['guild_id'];

    /**
     * Returns a collection of resolved users.
     *
     * @return CollectionInterfaceInterface|User[]|null Map of Snowflakes to user objects
     */
    protected function getUsersAttribute(): ?CollectionInterface
    {
        if (! isset($this->attributes['users'])) {
            return null;
        }

        $collection = ($this->discord->getCollectionClass())::for(User::class);

        foreach ($this->attributes['users'] as $snowflake => $user) {
            $collection->pushItem($this->discord->users->get('id', $snowflake) ?: $this->factory->part(User::class, (array) $user, true));
        }

        return $collection;
    }

    /**
     * Returns a collection of resolved members.
     *
     * Partial Member objects are missing user, deaf and mute fields
     *
     * @return CollectionInterfaceInterface|Member[]|null Map of Snowflakes to partial member objects
     */
    protected function getMembersAttribute(): ?CollectionInterface
    {
        if (! isset($this->attributes['members'])) {
            return null;
        }

        $collection = ($this->discord->getCollectionClass())::for(Member::class);

        foreach ($this->attributes['members'] as $snowflake => $member) {
            if ($guild = $this->discord->guilds->get('id', $this->guild_id)) {
                $memberPart = $guild->members->get('id', $snowflake);
            }

            if (! isset($memberPart)) {
                $member->user = $this->attributes['users']->$snowflake;
                $memberPart = $this->factory->part(Member::class, (array) $member + ['guild_id' => $this->guild_id], true);
            }

            $collection->pushItem($memberPart);
        }

        return $collection;
    }

    /**
     * Returns a collection of resolved roles.
     *
     * @return CollectionInterfaceInterface|Role[]|null Map of Snowflakes to role objects
     */
    protected function getRolesAttribute(): ?CollectionInterface
    {
        if (! isset($this->attributes['roles'])) {
            return null;
        }

        $collection = ($this->discord->getCollectionClass())::for(Role::class);

        foreach ($this->attributes['roles'] as $snowflake => $role) {
            if ($guild = $this->discord->guilds->get('id', $this->guild_id)) {
                $rolePart = $guild->roles->get('id', $snowflake);
            }

            if (! isset($rolePart)) {
                $rolePart = $this->factory->part(Role::class, (array) $role + ['guild_id' => $this->guild_id], true);
            }

            $collection->pushItem($rolePart);
        }

        return $collection;
    }

    /**
     * Returns a collection of resolved channels.
     *
     * Partial Channel objects only have id, name, type and permissions fields. Threads will also have thread_metadata and parent_id fields.
     *
     * @return CollectionInterfaceInterface|Channel[]|Thread[]|null Map of Snowflakes to partial channel objects
     */
    protected function getChannelsAttribute(): ?CollectionInterface
    {
        if (! isset($this->attributes['channels'])) {
            return null;
        }

        $collection = new ($this->discord->getCollectionClass())();

        foreach ($this->attributes['channels'] as $snowflake => $channel) {
            if ($guild = $this->discord->guilds->get('id', $this->guild_id)) {
                $channelPart = $guild->channels->get('id', $snowflake);
            }

            if (! isset($channelPart)) {
                if (in_array($channel->type, [Channel::TYPE_ANNOUNCEMENT_THREAD, Channel::TYPE_PRIVATE_THREAD, Channel::TYPE_PUBLIC_THREAD])) {
                    $channelPart = $this->factory->part(Thread::class, (array) $channel + ['guild_id' => $this->guild_id], true);
                } else {
                    $channelPart = $this->factory->part(Channel::class, (array) $channel + ['guild_id' => $this->guild_id], true);
                }
            }

            $collection->pushItem($channelPart);
        }

        return $collection;
    }

    /**
     * Returns a collection of resolved messages.
     *
     * @return CollectionInterfaceInterface|Message[]|null Map of Snowflakes to partial messages objects
     */
    protected function getMessagesAttribute(): ?CollectionInterface
    {
        if (! isset($this->attributes['messages'])) {
            return null;
        }

        $collection = ($this->discord->getCollectionClass())::for(Message::class);

        foreach ($this->attributes['messages'] as $snowflake => $message) {
            if ($guild = $this->discord->guilds->get('id', $this->guild_id)) {
                if ($channel = $guild->channels->get('id', $message->channel_id)) {
                    $messagePart = $channel->messages->get('id', $snowflake);
                }
            }

            $collection->pushItem($messagePart ?? $this->factory->part(Message::class, (array) $message + ['guild_id' => $this->guild_id], true));
        }

        return $collection;
    }

    /**
     * Returns a collection of resolved attachments.
     *
     * @return CollectionInterfaceInterface|Attachment[]|null Map of Snowflakes to attachments objects
     */
    protected function getAttachmentsAttribute(): ?CollectionInterface
    {
        if (! isset($this->attributes['attachments'])) {
            return null;
        }

        $attachments = ($this->discord->getCollectionClass())::for(Attachment::class);

        foreach ($this->attributes['attachments'] as $attachment) {
            $attachments->pushItem($this->factory->part(Attachment::class, (array) $attachment, true));
        }

        return $attachments;
    }
}
