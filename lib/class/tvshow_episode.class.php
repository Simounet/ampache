<?php
/* vim:set softtabstop=4 shiftwidth=4 expandtab: */
/**
 *
 * LICENSE: GNU General Public License, version 2 (GPLv2)
 * Copyright 2001 - 2014 Ampache.org
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License v2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

class TVShow_Episode extends Video
{
    public $original_name;
    public $season;
    public $episode_number;
    public $summary;

    public $f_link;
    public $f_season;
    public $f_season_link;
    public $f_tvshow;
    public $f_tvshow_link;

    /**
     * Constructor
     * This pulls the tv show episode information from the database and returns
     * a constructed object
     */
    public function __construct($id)
    {
        parent::__construct($id);

        $info = $this->get_info($id);
        foreach ($info as $key=>$value) {
            $this->$key = $value;
        }

        return true;

    }

    /**
     * gc
     *
     * This cleans out unused tv shows episodes
     */
    public static function gc()
    {
        $sql = "DELETE FROM `tvshow_episode` USING `tvshow_episode` LEFT JOIN `video` ON `video`.`id` = `tvshow_episode`.`id` WHERE `video`.`id` IS NULL";
        Dba::write($sql);
    }

    /**
     * insert
     * Insert a new tv show episode and related entities.
     */
    public static function insert(array $data, $gtypes = array(), $options = array())
    {
        if (empty($data['tv_show_name'])) {
            $data['tv_show_name'] = T_('Unknown');
        }
        $tags = $data['genre'];

        $tvshow = TVShow::check($data['tv_show_name'], $data['year']);
        if ($options['gather_art'] && $tvshow && $data['tvshow_art'] && !Art::has_db($tvshow, 'tvshow')) {
            $art = new Art($tvshow, 'tvshow');
            $art->insert_url($data['tvshow_art']);
        }
        $tv_season_id = TVShow_Season::check($tvshow, $data['tv_season']);
        if ($options['gather_art'] && $tvshow_season && $data['tvshow_season_art'] && !Art::has_db($tvshow_season, 'tvshow_season')) {
            $art = new Art($tvshow_season, 'tvshow_season');
            $art->insert_url($data['tvshow_season_art']);
        }

        if (is_array($tags)) {
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    Tag::add('tvshow_season', $tv_season_id, $tag, false);
                    Tag::add('tvshow', $tvshow, $tag, false);
                }
            }
        }

        $sdata = $data;
        // Replace relation name with db ids
        $sdata['tvshow'] = $tvshow;
        $sdata['tv_season_id'] = $tv_season_id;
        return self::create($sdata);
    }

    /**
     * create
     * This takes a key'd array of data as input and inserts a new tv show episode entry, it returns the record id
     */
    public static function create($data)
    {
        $sql = "INSERT INTO `tvshow_episode` (`id`, `original_name`, `season`, `episode_number`, `summary`) " .
            "VALUES (?, ?, ?, ?, ?)";
        Dba::write($sql, array($data['id'], $data['original_name'], $data['tv_season_id'], $data['tv_episode'], $data['summary']));

        return $data['id'];

    }

    /**
     * update
     * This takes a key'd array of data as input and updates a tv show episode entry
     */
    public function update(array $data)
    {
        parent::update($data);

        $original_name = $data['original_name'] ?: $this->original_name;
        $tvshow_season = $data['tvshow_season'] ?: $this->season;
        $tvshow_episode = $data['tvshow_episode'] ?: $this->episode_number;
        $summary = $data['summary'] ?: $summary;

        $sql = "UPDATE `tvshow_episode` SET `original_name` = ?, `season` = ?, `episode_number` = ?, `summary` = ? WHERE `id` = ?";
        Dba::write($sql, array($original_name, $tvshow_season, $tvshow_episode, $summary, $this->id));

        $this->original_name = $originale_name;
        $this->tvshow_season = $tvshow_season;
        $this->tvshow_episode = $tvshow_episode;
        $this->summary = $summary;

        return $this->id;

    }

    /**
     * format
     * this function takes the object and reformats some values
     */
    public function format()
    {
        parent::format();

        $season = new TVShow_Season($this->season);
        $season->format();

        $this->f_title = ($this->original_name ?: $this->f_title);
        $this->f_link = '<a href="' . $this->link . '">' . $this->f_title . '</a>';
        $this->f_season = $season->f_name;
        $this->f_season_link = $season->f_link;
        $this->f_tvshow = $season->f_tvshow;
        $this->f_tvshow_link = $season->f_tvshow_link;

        $this->f_file = $this->f_tvshow;
        if ($this->episode_number) {
            $this->f_file .= ' - S'. sprintf('%02d', $season->season_number) . 'E'. sprintf('%02d', $this->episode_number);
        }
        $this->f_file .= ' - ' . $this->f_title;
        $this->f_full_title = $this->f_file;

        return true;
    }

    public function get_keywords()
    {
        $keywords = parent::get_keywords();
        $keywords['tvshow'] = array('important' => true,
            'label' => T_('TV Show'),
            'value' => $this->f_tvshow);
        $keywords['tvshow_season'] = array('important' => false,
            'label' => T_('Season'),
            'value' => $this->f_season);
        if ($this->episode_number) {
            $keywords['tvshow_episode'] = array('important' => false,
                'label' => T_('Episode'),
                'value' => $this->episode_number);
        }
        $keywords['type'] = array('important' => false,
            'label' => null,
            'value' => 'tvshow'
        );

        return $keywords;
    }

    public function get_parent()
    {
        return array('object_type' => 'tvshow_season', 'object_id' => $this->season);
    }

    public function get_release_item_art()
    {
        return array('object_type' => 'tvshow_season',
            'object_id' => $this->season
        );
    }
}