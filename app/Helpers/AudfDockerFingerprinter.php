<?php

namespace Duplitron\Helpers;

use Duplitron\Helpers\Contracts\FingerprinterContract;
use Duplitron\Helpers\Contracts\LoaderContract;

use Duplitron\Media;
use Duplitron\Match;

class AudfDockerFingerprinter implements FingerprinterContract
{

    const AUDFPRINT_DOCKER_PATH = '/var/audfprint/';

    // TODO: We probably want to create a "matches" model
    const MATCH_POTENTIAL_TARGET = 'potential_targets';
    const MATCH_CORPUS = 'corpus';
    const MATCH_DISTRACTOR = 'distractors';
    const MATCH_TARGET = 'targets';

    // Database statuses
    const DATABASE_STATUS_FULL = 'full';
    const DATABASE_STATUS_GOOD = 'good';
    const DATABASE_STATUS_MISSING = 'missing';

    // Contracts
    protected $loader;

    public function __construct(LoaderContract $loader)
    {
        $this->loader = $loader;
    }

    /**
     * See contract for documentation
     */
    public function runMatch($media)
    {
        $afpt_files = $this->prepareMedia($media);
        $afpt_file = $afpt_files['full']; // We use the full file for matching
        $project = $media->project;

        $task_logs = [];
        $corpus_results = [];
        $potential_targets_results = [];
        $distractors_results = [];
        $targets_results = [];

        /////
        // Find all matches with stored items

        // Find matches with corpus items
        $databases = $this->getDatabases(AudfDockerFingerprinter::MATCH_CORPUS, $project);
        $results = $this->multiMatch($media, $databases);
        $task_logs = array_merge($task_logs, $results['logs']);
        $corpus_results = $results['results'];

        array_walk($corpus_results, function(&$result)
        {
            $result['type'] = AudfDockerFingerprinter::MATCH_CORPUS;
        });

        // Find matches with potential target items
        $databases = $this->getDatabases(AudfDockerFingerprinter::MATCH_POTENTIAL_TARGET, $project);
        $results = $this->multiMatch($media, $databases);
        $task_logs = array_merge($task_logs, $results['logs']);
        $potential_targets_results = $results['results'];
        array_walk($potential_targets_results, function(&$result)
        {
            $result['type'] = AudfDockerFingerprinter::MATCH_POTENTIAL_TARGET;
        });

        // Find matches with distractor items
        $databases = $this->getDatabases(AudfDockerFingerprinter::MATCH_DISTRACTOR, $project);
        $results = $this->multiMatch($media, $databases);
        $task_logs = array_merge($task_logs, $results['logs']);
        $distractors_results = $results['results'];
        array_walk($distractors_results, function(&$result)
        {
            $result['type'] = AudfDockerFingerprinter::MATCH_DISTRACTOR;
        });

        // Find matches with target items
        $databases = $this->getDatabases(AudfDockerFingerprinter::MATCH_TARGET, $project);
        $results = $this->multiMatch($media, $databases);
        $task_logs = array_merge($task_logs, $results['logs']);
        $targets_results = $results['results'];
        array_walk($targets_results, function(&$result)
        {
            $result['type'] = AudfDockerFingerprinter::MATCH_TARGET;
        });


        /////
        // Resolve the matches
        //

        // Create a master list of matches
        $matches = array_merge($corpus_results, $potential_targets_results, $distractors_results, $targets_results);

        // Save any previously unsaved matches
        foreach($matches as $match)
        {
            // To prevent mirror duplicates, stored matches always put the higher ID media as the destination
            if($match['destination_media']->id > $media->id)
            {
                $destination_id = $match['destination_media']->id;
                $destination_start = $match['target_start'];
                $source_id = $media->id;
                $source_start = $match['start'];
            }
            else
            {
                $source_id = $match['destination_media']->id;
                $source_start = $match['target_start'];
                $destination_id = $media->id;
                $destination_start = $match['start'];
            }

            // TODO: convert this to bulk insert, (eloquent doesn't seem to have an elegant way to bulk insert right now.)
            $match_object = new Match();
            $match_object->duration = $match['duration'];
            $match_object->destination_id = $destination_id;
            $match_object->destination_start = $destination_start;
            $match_object->source_id = $source_id;
            $match_object->source_start = $source_start;
            try
            {
                $match_object->save();
            }
            catch(\Exception $e)
            {
                // TODO: check to be sure the exception is a dupe key (which is OK)
            }
        }

        // Sort all matches list by start time
        $start_sort = function($a, $b)
        {
            if($a['start'] < $b['start'])
                return -1;
            if($a['start'] > $b['start'])
                return 1;
            return 0;
        };
        usort($matches, $start_sort);
        usort($corpus_results, $start_sort);
        usort($potential_targets_results, $start_sort);
        usort($distractors_results, $start_sort);
        usort($targets_results, $start_sort);

        // Consolidate matches involving the same file

        // Map the input file to segments based on the matches
        // Distractor -> Target -> Potential Target -> Corpus
        $segments = [];
        $start = -1;
        $end = -1;
        $type = '';
        $is_new_match = true;

        while($match = array_shift($matches))
        {
            $next_match = array_shift($matches);

            if($is_new_match)
            {
                // Brand new match
                $start = $match['start'];
                $end = $match['start'] + $match['duration'];
                $type = $match['type'];
                $is_new_match = false;
            }
            else
            {
                // Merge overlap
                $end = max($end, $match['start'] + $match['duration']);

                // Resolve types (combine and pick the dominant type)
                if($match['type'] == AudfDockerFingerprinter::MATCH_DISTRACTOR ||
                    $type == AudfDockerFingerprinter::MATCH_DISTRACTOR)
                    $type = AudfDockerFingerprinter::MATCH_DISTRACTOR;
                else if($match['type'] == AudfDockerFingerprinter::MATCH_TARGET ||
                    $type == AudfDockerFingerprinter::MATCH_TARGET)
                    $type = AudfDockerFingerprinter::MATCH_TARGET;
                else if($match['type'] == AudfDockerFingerprinter::MATCH_POTENTIAL_TARGET ||
                    $type == AudfDockerFingerprinter::MATCH_POTENTIAL_TARGET)
                    $type = AudfDockerFingerprinter::MATCH_POTENTIAL_TARGET;
                else if($match['type'] == AudfDockerFingerprinter::MATCH_CORPUS ||
                    $type == AudfDockerFingerprinter::MATCH_CORPUS)
                    $type = AudfDockerFingerprinter::MATCH_CORPUS;
            }

            if($next_match == null || $next_match['start'] > $end) {
                // We're done with this match, SHIP IT
                $final_match = [
                    'start' => $start,
                    'end' => $end,
                    'type' => $type
                ];

                array_push($segments, $final_match);
                $is_new_match = true;
            }

            // Add the next back to the list
            array_unshift($matches, $next_match);
        }

        $results = [
            "matches" => [
                "potential_targets" => $potential_targets_results,
                "corpus" => $corpus_results,
                "distractors" => $distractors_results,
                "targets" => $targets_results
            ],
            "segments" => $segments
        ];

        return array(
            'results' => $results,
            'output' => $task_logs
        );
    }

    /**
     * Given a media item and match type, add it to the database
     * @param [type] $media      [description]
     * @param [type] $match_type [description]
     */
    private function addDatabaseItem($media, $match_type)
    {
        $afpt_files = $this->prepareMedia($media);
        $project = $media->project;

        $database_path = $this->getCurrentDatabase($match_type, $project);

        // The file may be in parts, add each part individually
        // TODO: consider adding drop check to individual parts
        // (for now we will always have all parts added to the same database)

        // Obtain a file lock
        $lockfile = $this->touchFlockFile($database_path);
        if(flock($lockfile, LOCK_EX))
        {
            // Now that we're locked, make sure the path hasn't moved
            $database_path = $this->resolveDatabasePath($database_path);

            // Are we making someting new or not
            if($this->getDatabaseStatus($database_path) == AudfDockerFingerprinter::DATABASE_STATUS_MISSING)
                $audf_command = 'new';
            else
                $audf_command = 'add';

            // Add the corpus items
            $logs = array();
            foreach($afpt_files['chunks'] as $afpt_file) {

                $cmd = [$audf_command, '-d', AudfDockerFingerprinter::AUDFPRINT_DOCKER_PATH.$database_path, '--maxtime', '262144', '--density', '20', AudfDockerFingerprinter::AUDFPRINT_DOCKER_PATH.'afpt_cache/'.$afpt_file];

                // It is possible we just created a database, so the next clip would need to be added rather than overwritten.
                $audf_command = 'add';

                // Append the logs
                $logs = array_merge($logs, $this->runDocker($cmd));
            }

            // Did we fill the database?
            $drop_count = $this->getDropCount($logs);
            if($drop_count > 0)
            {
                $this->retireDatabase($database_path);
            }

            // Release the lock
            flock($lockfile, LOCK_UN);
        }
        else
        {
            throw \Exception("Couldn't obtain a lock for ".$database_path);
        }

        // Close the file
        fclose($lockfile);

        return array(
            'results' => true,
            'output' => $logs,
            'database' => $database_path
        );
    }


    /**
     * See contract for documentation
     */
    public function addCorpusItem($media)
    {
        // Make sure this media hasn't already been added
        if($media->is_corpus)
            return;

        // Run the database insertion
        $result = $this->addDatabaseItem($media, AudfDockerFingerprinter::MATCH_CORPUS);

        // Save the result
        $media->is_corpus = true;
        $media->corpus_database = $result['database'];
        $media->save();

        return $result;
    }


    /**
     * See contract for documentation
     */
    public function addDistractorsItem($media)
    {
        // Make sure this media hasn't already been added
        if($media->is_distractor)
            return;

        // Run the database insertion
        $result = $this->addDatabaseItem($media, AudfDockerFingerprinter::MATCH_DISTRACTOR);

        // Save the result
        $media->is_distractor = true;
        $media->distractor_database = $result['database'];
        $media->save();

        return $result;
    }


    /**
     * See contract for documentation
     */
    public function addPotentialTargetsItem($media)
    {
        // Make sure this media hasn't already been added
        if($media->is_potential_target)
            return;

        // Run the database insertion
        $result = $this->addDatabaseItem($media, AudfDockerFingerprinter::MATCH_POTENTIAL_TARGET);

        // Save the result
        $media->is_potential_target = true;
        $media->potential_target_database = $result['database'];
        $media->save();

        return $result;
    }


    /**
     * See contract for documentation
     */
    public function addTargetsItem($media)
    {
        // Make sure this media hasn't already been added
        if($media->is_target)
            return;

        // Run the database insertion
        $result = $this->addDatabaseItem($media, AudfDockerFingerprinter::MATCH_TARGET);

        // Save the result
        $media->is_target = true;
        $media->target_database = $result['database'];
        $media->save();

        return $result;
    }


    /**
     * See contract for documentation
     */
    public function removePotentialTargetsItem($media)
    {
        // Make sure this media is actually a target
        if(!$media->is_potential_target)
            $logs = array("This file isn't a potential target");
        else
        {
            // Resolve the path (in case it is filed)
            $database_path = $media->potential_target_database;
            $logs = $this->removeDatabaseItem($media, $database_path);
        }

        // Update the media file
        $media->is_potential_target = false;
        $media->potential_target_database = "";
        $media->save();

        return array(
            'results' => true,
            'output' => $logs
        );
    }


    /**
     * See contract for documentation
     */
    public function removeCorpusItem($media)
    {
        // Make sure this media is actually a target
        if(!$media->is_corpus)
            $logs = array("This file isn't a corpus item");
        else
        {
            // Resolve the path (in case it is filed)
            $database_path = $media->corpus_database;
            $logs = $this->removeDatabaseItem($media, $database_path);
        }

        // Update the media file
        $media->is_corpus = false;
        $media->corpus_database = "";
        $media->save();

        return array(
            'results' => true,
            'output' => $logs
        );
    }


    /**
     * See contract for documentation
     */
    public function removeTargetsItem($media)
    {
        // Make sure this media is actually a target
        if(!$media->is_target)
            $logs = array("This file isn't a target item");
        else
        {
            // Resolve the path (in case it is filed)
            $database_path = $media->target_database;
            $logs = $this->removeDatabaseItem($media, $database_path);
        }

        // Update the media file
        $media->is_target = false;
        $media->target_database = "";
        $media->save();

        return array(
            'results' => true,
            'output' => $logs
        );
    }


    /**
     * See contract for documentation
     */
    public function removeDistractorsItem($media)
    {
        // Make sure this media is actually a target
        if(!$media->is_distractor)
            $logs = array("This file isn't a distractor item");
        else
        {
            // Resolve the path (in case it is filed)
            $database_path = $media->distractor_database;
            $logs = $this->removeDatabaseItem($media, $database_path);
        }

        // Update the media file
        $media->is_distractor = false;
        $media->distractor_database = "";
        $media->save();

        return array(
            'results' => true,
            'output' => $logs
        );
    }


    /**
     * Given a media object and a match type, remove the media object from the relevant database
     * @param  [type] $media     [description]
     * @param  [type] $matchType [description]
     * @return [type]            [description]
     */
    private function removeDatabaseItem($media, $database_path)
    {
        // Get the fingerprints
        $afpt_files = $this->prepareMedia($media);

        // Does the database still exist?
        // Obtain a file lock
        $lockfile = $this->touchFlockFile($database_path);
        if(flock($lockfile, LOCK_EX))
        {
            // Now that we're locked, make sure the path hasn't moved
            $database_path = $this->resolveDatabasePath($database_path);

            if($this->getDatabaseStatus($database_path) == AudfDockerFingerprinter::DATABASE_STATUS_MISSING)
                $logs = array("The database this was stored in no longer existed.");
            else
            {

                // Delete each chunk
                foreach($afpt_files['chunks'] as $afpt_file)
                {
                    $audf_command = 'remove';
                    $cmd = [$audf_command, '-d', AudfDockerFingerprinter::AUDFPRINT_DOCKER_PATH.$database_path, AudfDockerFingerprinter::AUDFPRINT_DOCKER_PATH.'afpt_cache/'.$afpt_file];
                    $logs = $this->runDocker($cmd);
                }
            }

            // Release the lock
            flock($lockfile, LOCK_UN);
        }
        else
        {
            throw \Exception("Couldn't obtain a lock for ".$database_path);
        }

        // Close the file
        fclose($lockfile);
        return $logs;
    }


    /**
     * Given a match type and media file, return the name of the next database to use
     * @param  string $match_type the match type being used
     * @param  object $project     the project being used
     * @return string            the name of the fingerprint database being used
     */
    private function getCurrentDatabase($match_type, $project) {

        // Make sure this is a valid match type
        $valid_types = [
            AudfDockerFingerprinter::MATCH_POTENTIAL_TARGET,
            AudfDockerFingerprinter::MATCH_CORPUS,
            AudfDockerFingerprinter::MATCH_DISTRACTOR,
            AudfDockerFingerprinter::MATCH_TARGET
        ];
        if(!in_array($match_type, $valid_types))
        {
            return;
        }

        // Make the cache for this type of match if it doesn't exist
        $cache_path = 'pklz_cache/'.$project->id.'/'.$match_type;
        if(!is_dir(env('FPRINT_STORE').$cache_path))
        {
            mkdir(env('FPRINT_STORE').$cache_path, 0777, true);

            // We have to manually set the mode unfortunately
            chmod(env('FPRINT_STORE').'pklz_cache/'.$project->id, 0777);
            chmod(env('FPRINT_STORE').$cache_path, 0777);
        }

        // Get a list of caches that already exist
        $databases = scandir(env('FPRINT_STORE').$cache_path);

        // Get the most recent database from the list
        $current_database = array_pop($databases);

        // Different corpus types have different naming structures algorithms
        switch($match_type)
        {
            // Corpus has a new set of buckets every day
            case AudfDockerFingerprinter::MATCH_CORPUS:
                $base = date('Y_m_d').'-project_'.$project->id.'-'.$match_type;
                break;

            // All others have a single set of buckets
            case AudfDockerFingerprinter::MATCH_DISTRACTOR:
            case AudfDockerFingerprinter::MATCH_TARGET:
            case AudfDockerFingerprinter::MATCH_POTENTIAL_TARGET:
                $base = 'project_'.$project->id.'-'.$match_type;
                break;
        }
        // If the current database recent and considered good, use it
        if(strpos($current_database, $base) === false)
            $bin_number = 0;
        elseif($this->getDatabaseStatus($cache_path.'/'.$current_database) == AudfDockerFingerprinter::DATABASE_STATUS_GOOD)
            return $cache_path."/".$current_database;
        else
        {
            // The current database is recent but filled, increment the bin number accordingly
            preg_match('/.*\-(\d+)(\-full)\.pklz/', $current_database, $matches);

            if(sizeof($matches) == 0)
                $bin_number = 0;
            else
                $bin_number = $matches[1] + 1;
        }

        // We have a winner!
        return $cache_path.'/'.$base."-".$bin_number.".pklz";
    }


    /**
     * Given a match type and media file return a list of all active fingerprint databases for the match type and project
     * @param  string $match_type   the match type being used
     * @param  object $project      the project being used
     * @param  boolean $only_active only relevant for match types where a database can become inactive, determines the filter to use.
     * @return array(string)        an array of paths to databases
     */
    private function getDatabases($match_type, $project, $only_active = false) {

        // Set up the cache path
        $cache_path = 'pklz_cache/'.$project->id.'/'.$match_type;
        if(!is_dir(env('FPRINT_STORE').$cache_path))
            return [];

        $databases = scandir(env('FPRINT_STORE').$cache_path);

        // Drop the first two items ('..'' and '.')
        unset($databases[0]);
        unset($databases[1]);

        // TODO: Filter corpus databases based on time

        // Prefix the cache path to each item
        foreach ($databases as &$value)
            $value = $cache_path.'/'.$value;

        return $databases;
    }


    /**
     * Given a path to an audf database, check the status of that database
     * @param  string $relative_database_path a relative path (from the audf cache) of the database
     * @return string                         the status (based on status constants defined in class)
     */
    private function getDatabaseStatus($relative_database_path) {
        // Has this been flagged as full?
        if(preg_match('/.*(\-full)\.pklz/', $relative_database_path))
            return AudfDockerFingerprinter::DATABASE_STATUS_FULL;

        // Does this exist at all?
        if(!file_exists(env('FPRINT_STORE').$relative_database_path ))
            return AudfDockerFingerprinter::DATABASE_STATUS_MISSING;

        // Looks good to me!
        return AudfDockerFingerprinter::DATABASE_STATUS_GOOD;
    }

    /**
     * Since we are storing status in the file path we may need to update a DB path
     * TODO: eventually status should be handled in... a database...
     * @param  [type] $relative_database_path [description]
     * @return [type]                         [description]
     */
    private function resolveDatabasePath($relative_database_path) {
        if(is_file(env('FPRINT_STORE').$relative_database_path))
            return $relative_database_path;

        // Try adding full to see if that exists
        $new_path = str_replace('.pklz', '-full.pklz', $relative_database_path);
        if(is_file(env('FPRINT_STORE').$new_path))
            return $new_path;

        return $relative_database_path;
    }



    /**
     * Given a database, mark it as full
     * @param  string $database_path The database to retire
     * @return null
     */
    private function retireDatabase($database_path)
    {
        // Is this already retired?
        if(strpos($database_path, '-full') !== false)
            return;

        // For now we mark a DB as full using it's filepath
        $new_path = str_replace('.pklz', '-full.pklz', $database_path);
        rename(env('FPRINT_STORE').$database_path, env('FPRINT_STORE').$new_path);
    }


    /**
     * Run a match against multiple databases at once, and combine the results
     * @param  object        $media     The media file being matched
     * @param  array(string) $databases Relative paths to the databases being matched against
     * @return object                   The 'results' and 'logs' from the matches.
     */
    private function multiMatch($media, $databases)
    {
        $afpt_files = $this->prepareMedia($media);
        $afpt_file = $afpt_files['full'];
        $results = array();
        $logs = array();
        // Run the match for each database
        foreach($databases as $database)
        {
            // Obtain a lock on the database
            $lockfile = $this->touchFlockFile($database);
            if(flock($lockfile, LOCK_SH))
            {
                // Now that we're locked, make sure the path hasn't moved
                $database = $this->resolveDatabasePath($database);

                // Run the match
                $cmd = ['match', '-d', AudfDockerFingerprinter::AUDFPRINT_DOCKER_PATH.$database, '--find-time-range', '-x', '1000', '--match-win', '2', AudfDockerFingerprinter::AUDFPRINT_DOCKER_PATH.'afpt_cache/'.$afpt_file];
                $match_logs = $this->runDocker($cmd);

                // Release the lock
                flock($lockfile, LOCK_UN);
            }
            else
            {
                throw \Exception("Couldn't obtain a lock for ".$database);
            }

            // Close the file
            fclose($lockfile);

            $match_results = $this->processAudfMatchLog($match_logs);

            // Remove results that point to this particular media file
            $match_results = $this->removeMatches($media, $match_results);

            $logs = array_merge($logs, $match_logs);
            $results = array_merge($results, $match_results);
        }


        // Return the logs and results
        return array(
            'results' => $results,
            'logs' => $logs
        );
    }


    /**
     * Takes in a media file and ensures that has been properly loaded and preprocessed
     * @param  object $media the media object we're going to be working with
     * @return array         a list of fingerprint file names related to this media
     */
    private function prepareMedia($media)
    {
        // First attempt to load the fingerprints
        $fprint_files = $this->loader->loadFingerprints($media);

        if($fprint_files == null) {
            // If fingerprints aren't properly provided, we will have to generate them
            // Load the media
            $media_files = $this->loader->loadMedia($media);
            $media_path_base = env('FPRINT_STORE').'media_cache/';
            $fprint_path_base = env('FPRINT_STORE').'afpt_cache/';

            $fprint_files = array(
                'full' => '',
                'chunks' => array()
            );

            // Figure out what fingerprints we're missing
            $needed_fingerprints = array();
            $parsed_path = pathinfo($media_files['full']);

            // Check out the full file first
            $full_afpt_file = $parsed_path['filename'].'.afpt';
            if(!is_file($fprint_path_base.$full_afpt_file))
                $fprint_files['full'] = $this->createFingerprint($media_files['full']);
            else
                $fprint_files['full'] = $full_afpt_file;

            // Now try each chunk
            foreach($media_files['chunks'] as $chunk_file)
            {
                $parsed_path = pathinfo($chunk_file);
                $chunk_afpt_file = $parsed_path['filename'].'.afpt';

                if(!is_file($fprint_path_base.$chunk_afpt_file))
                    $fprint_files['chunks'][] = $this->createFingerprint($chunk_file);
                else
                    $fprint_files['chunks'][] = $chunk_afpt_file;
            }
        }

        // Move the precompute file
        return $fprint_files;
    }


    /**
     * Takes in a media file and creates a fingerprint
     * @param  string $media_file the filename, not the pathname, of a media file in the media cache
     * @return array              the ['logs'] and resulting fingerprint ['file']
     */
    private function createFingerprint($media_file) {

        $cmd = ['precompute', '--density', '20', AudfDockerFingerprinter::AUDFPRINT_DOCKER_PATH.'media_cache/'.$media_file];
        $logs = $this->runDocker($cmd);

        $parsed_path = pathinfo($media_file);
        $fprint_file = $parsed_path['filename'].".afpt";
        $fprint_start_path = env('FPRINT_STORE').'media_cache/'.$fprint_file;
        $fprint_end_path = env('FPRINT_STORE').'afpt_cache/'.$fprint_file;
        if(is_file($fprint_start_path))
        {
            rename($fprint_start_path, $fprint_end_path);
            return $fprint_file;
        }
        else
            throw new \Exception('Could not create a fingerprint: '.$fprint_file);

        return '';
    }


    /**
     * Run a docker image
     * @param  string[] $cmd The list of commands to invoke in the docker image
     */
    private function runDocker($cmd) {

        // Create a connection with docker
        $docker = new \Docker\Docker(\Docker\Http\DockerClient::createWithEnv());

        // Create the docker container
        $container = new \Docker\Container(
            [
                'Image' => env('DOCKER_FPRINT_IMAGE'),
                'Cmd' => $cmd,
                'Volumes' => [
                    '/var/audfprint' => []
                ],
                'HostConfig' => [
                    'Binds' => [env('FPRINT_STORE').':'.AudfDockerFingerprinter::AUDFPRINT_DOCKER_PATH]
                ]
            ]
        );

        $manager = $docker->getContainerManager();
        //$manager->create($container);

        // Gather the logs and return them to the caller
        $logs = [];
        $manager->run($container, function($output, $type) use (&$logs) {
            // TODO: Process output more intelligently...
            $logs = array_merge($logs,explode("\n", $output));
        });

        // Clean up after yourself, it's only polite
        $manager->stop($container);
        $manager->remove($container, false, true);

        return $logs;
    }

    /**
     * TODO: This method will hopefully be deleted some day, and audfprint will just return structured data
     * For now it takes the output from an audfprint match operation and returns a structured list of matches
     * @param  string[] $logs The log output from an audf process
     * @return object[]       The list of matches
     */
    private function processAudfMatchLog($logs)
    {
        $matches = [];
        foreach($logs as $line)
        {

            $match_pattern = '/Matched\s+(\S+)\s+s\s+starting\s+at\s+(\S+)\s+s\s+in\s+(\S+)\s+to\s+time\s+(\S+)\s+s\s+in\s+(\S+)\s+with\s+(\S+)\s+of\s+(\S+)\s+common\s+hashes\s+at\s+rank\s+(\S+)/';
            if(preg_match($match_pattern, $line, $match_data))
            {
                // TODO: This object sctructure should be defined somewhere... not randomly in the middle of code
                // matched_file -> the filename of the match
                // duration -> the duration of the match
                // start -> where in the INPUT file the match starts
                // target_start -> where in the MATCHED file the match starts

                // The file name has a distinct pattern that contains the media ID in the system
                $file_pattern = '/.*media\-(\d*)\_(\d*)\..*/';
                preg_match($file_pattern, $match_data[5], $file_data);
                $destination_media = Media::find($file_data[1]);

                // Remove chunk information from matched file
                $matched_file = str_replace('_'.$file_data[2], '', $match_data[5]);

                $match = [
                    "matched_file" => $matched_file,
                    "duration" => floatval($match_data[1]),
                    "start" => floatval($match_data[2]),
                    "target_start" => floatval($match_data[4]) + floatval($file_data[2]) * env('FPRINT_CHUNK_LENGTH'),
                    "destination_media" => $destination_media
                ];

                // Combine matches across chunks

                // We don't want matches that are too short
                if($match['duration'] < 2)
                    continue;

                array_push($matches, $match);
            }
        }

        return $matches;
    }

    private function getDropCount($logs)
    {
        $drop_count = 0;
        foreach($logs as $line)
        {
            $match_pattern = '/Dropped\shashes\=\s(\d+)/';

            // Did we reach a log line that talks about drop count?
            if(preg_match($match_pattern, $line, $match_data))
            {
                $drop_count = $match_data[1];
                break;
            }
        }

        return $drop_count;
    }


    /**
     * Removes matches to a specific media file
     * @param  object $media   The media file we don't want matches to
     * @param  array  $matches The list of matches
     * @return array           The updated match list
     */
    private function removeMatches($media, $matches)
    {
        $new_matches = array();

        foreach($matches as $match)
        {
            // Make sure destination media exists
            if(!$match['destination_media'])
                continue;

            // We don't want matches to this media file
            // TODO: consider re-adding prevented matches with base media
            // || $match['destination_media']->base_media_id == $media->id
            if($match['destination_media']->id == $media->id)
                continue;

            $new_matches[] = $match;
        }

        return $new_matches;
    }


    /**
     * Opens a file, making sure it exists in the process
     * @param  string $path The string to the path being opened
     * @return
     */
    private function touchFlockFile($path) {
        // remove the "-full" status -- we want to lock it regardless of how filled it is.
        $path = str_replace('-full','', $path);
        $flock_path = env('FPRINT_STORE').'flocks/'.str_replace('/', '_', $path)."flock";
        if(file_exists($flock_path))
            return fopen($flock_path, 'r+');
        return fopen($flock_path, 'w+');
    }
}
?>

