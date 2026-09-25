<?php

declare(strict_types=1);

namespace App\Security\Acl;

/**
 * EZScore authorization attributes.
 *
 * Inspired by OPUS ACL privileges, implemented as native Symfony Security attributes.
 * Controllers and Twig must ask for these attributes instead of duplicating business rules.
 */
final class AclPrivilege
{
    private function __construct()
    {
    }

    public const PLAYLIST_VIEW = 'PLAYLIST_VIEW';
    public const PLAYLIST_EDIT = 'PLAYLIST_EDIT';
    public const PLAYLIST_DELETE = 'PLAYLIST_DELETE';
    public const PLAYLIST_ADD_SONG = 'PLAYLIST_ADD_SONG';
    public const PLAYLIST_REMOVE_SONG = 'PLAYLIST_REMOVE_SONG';
    public const PLAYLIST_REORDER = 'PLAYLIST_REORDER';
    public const PLAYLIST_INVITE = 'PLAYLIST_INVITE';
    public const PLAYLIST_REVOKE_SHARE = 'PLAYLIST_REVOKE_SHARE';

    public const GROUP_CREATE = 'GROUP_CREATE';
    public const GROUP_VIEW = 'GROUP_VIEW';
    public const GROUP_EDIT = 'GROUP_EDIT';
    public const GROUP_DELETE = 'GROUP_DELETE';
    public const GROUP_MANAGE_MEMBERS = 'GROUP_MANAGE_MEMBERS';
    public const GROUP_DELEGATE = 'GROUP_DELEGATE';

    public const SONG_VIEW = 'SONG_VIEW';
    public const SONG_EDIT = 'SONG_EDIT';
    public const SONG_PUBLISH = 'SONG_PUBLISH';
    public const SONG_REPLACE_AUDIO = 'SONG_REPLACE_AUDIO';
}
