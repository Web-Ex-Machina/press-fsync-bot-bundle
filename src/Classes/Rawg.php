<?php

namespace WEM\PressFsyncBotBundle\Classes;

class Rawg
{
    public static function getGame($strSlug, $blnDisableCache = false)
    {
        $encryptionService = \System::getContainer()->get('plenta.encryption');
        $strApiKey = $encryptionService->decrypt(\Config::get('pfsRawgApiSecret'));

        $strSlug = str_replace('.', '', $strSlug);
        $strSlug = \StringUtil::generateAlias($strSlug);
        $strGamePath = 'files/rawg/'.substr($strSlug, 0, 1).'/'.$strSlug.'.json';
        $objCache = new \File($strGamePath);

        // Check if we have a file with the data
        if ($objCache->exists() && !$blnDisableCache) {
            $objGame = $objCache->getContent();
            $g = (array) json_decode($objGame);

            if ('Not found.' == $g['detail']) {
                $g['name'] = $name;
            }
        } else {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.rawg.io/api/games/'.$strSlug.'?key='.$strApiKey);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $objGame = curl_exec($ch);
            curl_close($ch);

            $blnGameFound = false;
            $g = (array) json_decode($objGame);

            // Try to search the game instead
            if ('Not found.' == $g['detail']) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.rawg.io/api/games?search='.$strSlug.'&key='.$strApiKey);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $objResults = json_decode(curl_exec($ch));
                curl_close($ch);

                if (!empty($objResults->results)) {
                    foreach ($objResults->results as $r) {
                        if ($r->name == $name) {
                            $g = (array) $r;
                            $blnGameFound = true;
                            break;
                        }
                    }

                    $g['name'] = $name;
                }
            } else {
                $blnGameFound = true;
            }

            $platforms = [];
            if (is_array($g['platforms']) && !empty($g['platforms'])) {
                foreach ($g['platforms'] as $key => $v) {
                    $platforms[] = $v->platform->name;
                }
                sort($platforms);
            }
            $g['platforms'] = $platforms;

            $developers = [];
            if (is_array($g['developers']) && !empty($g['developers'])) {
                foreach ($g['developers'] as $key => $v) {
                    $developers[] = $v->name;
                }
            }
            $g['developers'] = $developers;

            $publishers = [];
            if (is_array($g['publishers']) && !empty($g['publishers'])) {
                foreach ($g['publishers'] as $key => $v) {
                    $publishers[] = $v->name;
                }
            }
            $g['publishers'] = $publishers;

            $genres = [];
            if (is_array($g['genres']) && !empty($g['genres'])) {
                foreach ($g['genres'] as $key => $v) {
                    $genres[] = $v->name;
                }
            }
            $g['genres'] = $genres;

            $tags = [];
            if (is_array($g['tags']) && !empty($g['tags'])) {
                foreach ($g['tags'] as $key => $v) {
                    $tags[] = $v->name;
                }
            }
            $g['tags'] = $tags;

            if ($blnGameFound && !$blnDisableCache) {
                $objCache->truncate();
                $objCache->write(json_encode($g));
                $objCache->close();
            }
        }

        return $g;
    }
}
