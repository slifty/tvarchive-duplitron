<?php

namespace Duplitron\Helpers;

use Duplitron\Helpers\Contracts\FingerprinterContract;
use Duplitron\Helpers\Contracts\LoaderContract;

use Duplitron\Media;
use Duplitron\Match;

class AudfprintFingerprinter implements FingerprinterContract
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
    public function runMatch($media, $only_active=false)
    {
        $task_logs = [];
        $corpus_results = [];
        $potential_targets_results = [];
        $distractors_results = [];
        $targets_results = [];

        $task_logs[] = $this->logLine("Starting");
        $task_logs[] = $this->logLine("Start: Prepare media");
        $afpt_files = $this->prepareMedia($media);
        $afpt_file = $afpt_files['full']; // We use the full file for matching
        $project = $media->project;
        $task_logs[] = $this->logLine("End:   Prepare media");

        /////
        // Find all matches with stored items

        // Find matches with corpus items
        $task_logs[] = $this->logLine("Start: Corpus multimatch");
        $databases = $this->getDatabases(AudfprintFingerprinter::MATCH_CORPUS, $media, $only_active);
        $results = $this->multiMatch($media, $databases);
        $task_logs = array_merge($task_logs, $results['logs']);
        $corpus_results = $results['results'];
        array_walk($corpus_results, function(&$result)
        {
            $result['type'] = AudfprintFingerprinter::MATCH_CORPUS;
        });
        $task_logs[] = $this->logLine("End:   Corpus multimatch");

        // Find matches with potential target items
        $task_logs[] = $this->logLine("Start: Potential target multimatch");
        $databases = $this->getDatabases(AudfprintFingerprinter::MATCH_POTENTIAL_TARGET, $media, $only_active);
        $results = $this->multiMatch($media, $databases);
        $task_logs = array_merge($task_logs, $results['logs']);
        $potential_targets_results = $results['results'];
        array_walk($potential_targets_results, function(&$result)
        {
            $result['type'] = AudfprintFingerprinter::MATCH_POTENTIAL_TARGET;
        });
        $task_logs[] = $this->logLine("End:   Corpus multimatch");

        // Find matches with distractor items
        $task_logs[] = $this->logLine("Start: Distractor multimatch");
        $databases = $this->getDatabases(AudfprintFingerprinter::MATCH_DISTRACTOR, $media, $only_active);
        $results = $this->multiMatch($media, $databases);
        $task_logs = array_merge($task_logs, $results['logs']);
        $distractors_results = $results['results'];
        array_walk($distractors_results, function(&$result)
        {
            $result['type'] = AudfprintFingerprinter::MATCH_DISTRACTOR;
        });
        $task_logs[] = $this->logLine("End:   Corpus multimatch");

        // Find matches with target items
        $task_logs[] = $this->logLine("Start: Target multimatch");
        $databases = $this->getDatabases(AudfprintFingerprinter::MATCH_TARGET, $media, $only_active);
        $results = $this->multiMatch($media, $databases);
        $task_logs = array_merge($task_logs, $results['logs']);
        $targets_results = $results['results'];
        array_walk($targets_results, function(&$result)
        {
            $result['type'] = AudfprintFingerprinter::MATCH_TARGET;
        });
        $task_logs[] = $this->logLine("End:   Target multimatch");


        /////
        // Resolve the matches
        //

        // Create a master list of matches
        $matches = array_merge($corpus_results, $potential_targets_results, $distractors_results, $targets_results);

        $task_logs[] = $this->logLine("Start: Save Matches");
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
        $task_logs[] = $this->logLine("End:   Save Matches");


        $task_logs[] = $this->logLine("Start: Resolve Matches");
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
                if($match['type'] == AudfprintFingerprinter::MATCH_DISTRACTOR ||
                    $type == AudfprintFingerprinter::MATCH_DISTRACTOR)
                    $type = AudfprintFingerprinter::MATCH_DISTRACTOR;
                else if($match['type'] == AudfprintFingerprinter::MATCH_TARGET ||
                    $type == AudfprintFingerprinter::MATCH_TARGET)
                    $type = AudfprintFingerprinter::MATCH_TARGET;
                else if($match['type'] == AudfprintFingerprinter::MATCH_POTENTIAL_TARGET ||
                    $type == AudfprintFingerprinter::MATCH_POTENTIAL_TARGET)
                    $type = AudfprintFingerprinter::MATCH_POTENTIAL_TARGET;
                else if($match['type'] == AudfprintFingerprinter::MATCH_CORPUS ||
                    $type == AudfprintFingerprinter::MATCH_CORPUS)
                    $type = AudfprintFingerprinter::MATCH_CORPUS;
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
        $task_logs[] = $this->logLine("End:   Resolve Matches");


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
        $task_logs = array();

        $task_logs[] = $this->logLine("Starting");
        $task_logs[] = $this->logLine("Start: Prepare media");
        $afpt_files = $this->prepareMedia($media);
        $task_logs[] = $this->logLine("End:   Prepare media");

        // Get current database locks and returns a valid database for us to write to
        $task_logs[] = $this->logLine("Start: Identify and lock target database");
        $database_object = $this->getCurrentDatabase($match_type, $media);
        $database_path = $database_object['path'];
        $lockfile = $database_object['lockfile'];
        $task_logs[] = $this->logLine("End:   Identify and lock target database");

        // Now that we're locked, make sure the path hasn't moved
        $database_path = $this->resolveDatabasePath($database_path);

        // Are we making someting new or not
        if($this->getDatabaseStatus($database_path) == AudfprintFingerprinter::DATABASE_STATUS_MISSING)
            $audf_command = 'new';
        else
            $audf_command = 'add';

        // Add the corpus items
        foreach($afpt_files['chunks'] as $afpt_file) {

            $task_logs[] = $this->logLine("Start: Storing fingerprints for ".$afpt_file);
            $cmd = [$audf_command, '-d', $this->resolveCachePath($database_path), '--maxtime', '262144', '--density', '20', $this->resolveCachePath('afpt_cache/'.$afpt_file)];

            // It is possible we just created a database, so the next clip would need to be added rather than overwritten.
            $audf_command = 'add';

            // Append the logs
            $task_logs = array_merge($task_logs, $this->runAudfprint($cmd));
            $task_logs[] = $this->logLine("End:   Storing fingerprints for ".$afpt_file);
        }

        // Did we fill the database?
        $drop_count = $this->getDropCount($task_logs);
        if($drop_count > 0)
        {
            $this->retireDatabase($database_path);
        }

        // Release the lock
        flock($lockfile, LOCK_UN);

        // Close the file
        fclose($lockfile);

        return array(
            'results' => true,
            'output' => $task_logs,
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
            throw new \Exception("This is already a corpus item");

        // Run the database insertion
        $result = $this->addDatabaseItem($media, AudfprintFingerprinter::MATCH_CORPUS);

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
            throw new \Exception("This is already a distractor");

        // Run the database insertion
        $result = $this->addDatabaseItem($media, AudfprintFingerprinter::MATCH_DISTRACTOR);

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
            throw new \Exception("This is already a potential target");

        // Run the database insertion
        $result = $this->addDatabaseItem($media, AudfprintFingerprinter::MATCH_POTENTIAL_TARGET);

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
            throw new \Exception("This is already a target");

        // Run the database insertion
        $result = $this->addDatabaseItem($media, AudfprintFingerprinter::MATCH_TARGET);

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
        $task_logs = array();

        // Get the fingerprints
        $task_logs[] = $this->logLine("Starting");
        $task_logs[] = $this->logLine("Start: Prepare media");
        $afpt_files = $this->prepareMedia($media);
        $task_logs[] = $this->logLine("End:   Prepare media");

        // Does the database still exist?
        // Obtain a file lock
        $task_logs[] = $this->logLine("Start: Obtaining file lock for ".$database_path);
        $lockfile = $this->touchFlockFile($database_path);
        if(flock($lockfile, LOCK_EX))
        {
            $task_logs[] = $this->logLine("End:   Obtaining file lock for ".$database_path);
            // Now that we're locked, make sure the path hasn't moved
            $database_path = $this->resolveDatabasePath($database_path);

            if($this->getDatabaseStatus($database_path) == AudfprintFingerprinter::DATABASE_STATUS_MISSING)
                $task_logs[] = "The database this was stored in no longer existed.";
            else
            {

                // Delete each chunk
                foreach($afpt_files['chunks'] as $afpt_file)
                {

                    $task_logs[] = $this->logLine("Start: Removing ".$afpt_file);
                    $audf_command = 'remove';
                    $cmd = [$audf_command, '-d', $this->resolveCachePath($database_path), $this->resolveCachePath('afpt_cache/'.$afpt_file)];
                    $task_logs = array_merge($task_logs, $this->runAudfprint($cmd));
                    $task_logs[] = $this->logLine("End:   Removing ".$afpt_file);
                }
            }

            // Release the lock
            flock($lockfile, LOCK_UN);
        }
        else
        {
            throw new \Exception("Couldn't obtain a lock for ".$database_path);
        }

        // Close the file
        fclose($lockfile);
        return $task_logs;
    }



    /**
     * See contract for documentation
     */
    public function cleanUp($media)
    {
        $task_logs = array();

        $task_logs[] = $this->logLine("Start: Removing cached files");
        $results = $this->loader->removeCachedFiles($media);
        $task_logs[] = $this->logLine("End:   Removing cached files");

        return array(
            'results' => $results,
            'output' => $task_logs
        );

    }


    /**
     * Given a match type and media file, return the name of the next database to use
     * @param  string $match_type the match type being used
     * @param  object $media      the media being used
     * @return string             the name of the fingerprint database being used
     */
    private function getCurrentDatabase($match_type, $media) {

        // Make sure this is a valid match type
        $valid_types = [
            AudfprintFingerprinter::MATCH_POTENTIAL_TARGET,
            AudfprintFingerprinter::MATCH_CORPUS,
            AudfprintFingerprinter::MATCH_DISTRACTOR,
            AudfprintFingerprinter::MATCH_TARGET
        ];
        if(!in_array($match_type, $valid_types))
        {
            return;
        }

        // Load the project
        $project = $media->project;

        // Make the cache for this type of match if it doesn't exist
        $cache_path = 'pklz_cache/'.$project->id.'/'.$match_type;
        if(!is_dir(env('FPRINT_STORE').$cache_path))
        {
            mkdir(env('FPRINT_STORE').$cache_path, 0777, true);

            // We have to manually set the mode unfortunately
            chmod(env('FPRINT_STORE').'pklz_cache/'.$project->id, 0777);
            chmod(env('FPRINT_STORE').$cache_path, 0777);
        }

        // Get a list of databases that already exist
        $databases = scandir(env('FPRINT_STORE').$cache_path);

        // Get the most recent database from the list
        $current_database = array_pop($databases);

        // Get the base name for this media file / corpus type
        switch($match_type)
        {
            // Corpus has a new set of buckets every day
            case AudfprintFingerprinter::MATCH_CORPUS:
                $base_time = $this->getBaseTime($media);
                $base = date('Y_m_d', $base_time).'-project_'.$project->id.'-'.$match_type;
                break;

            // All others have a single set of buckets
            case AudfprintFingerprinter::MATCH_DISTRACTOR:
            case AudfprintFingerprinter::MATCH_TARGET:
            case AudfprintFingerprinter::MATCH_POTENTIAL_TARGET:
                $base = 'project_'.$project->id.'-'.$match_type;
                break;
        }

        // Get a a list of databases that already exist
        $database_glob_path = $cache_path.'/'.$base.'*';
        $database_paths = glob($database_glob_path);

        // Separate out the bin numbers
        $empty_bins = array();
        $full_bins = array();
        foreach($database_paths as $database_path)
        {
            $database = basename($database_path);
            preg_match('/.*\-(\d+)(\-full)\.pklz/', $database, $matches);

            if(sizeof($matches) == 0)
                continue;

            $bin_number = $matches[1];
            if(strpos($database, '-full') === false)
                $empty_bins[] = (int)$bin_number;
            else
                $full_bins[] = (int)$bin_number;
        }

        // Make sure the last element is the highest bin number
        sort($empty_bins);
        sort($full_bins);

        // Set up our return value
        $database_object = array(
            'path' => '',
            'lockfile' => null
        );

        // Try to lock an empty bin
        foreach($empty_bins as $empty_bin)
        {
            // Nail down the bins
            $database_path = $cache_path.'/'.$base."-".$empty_bin.".pklz";;
            $lockfile = $this->touchFlockFile($database_path);

            // Take the first bin we can lock
            if(flock($lockfile, LOCK_EX | LOCK_NB, $wouldblock) || !$wouldblock)
            {
                $database_object['path'] = $database_path;
                $database_object['lockfile'] = $lockfile;
                return $database_object;
            }
        }

        // If we're here we couldn't lock an empty bin and need to create a new one
        $next_bin = max(array_pop($empty_bins), array_pop($full_bins));

        // Keep looping until we can get a lock
        do
        {
            $next_bin += 1; // Note: the start value of next bin is actually the most recent ACTIVE bin, we want new ones
            $database_path = $cache_path.'/'.$base."-".$next_bin.".pklz";
            $lockfile = $this->touchFlockFile($database_path);
        } while(!flock($lockfile, LOCK_EX | LOCK_NB, $wouldblock) && $wouldblock);

        $database_object['path'] = $database_path;
        $database_object['lockfile'] = $lockfile;
        return $database_object;
    }


    /**
     * Given a match type and media file return a list of all active fingerprint databases for the match type and project
     * @param  string $match_type   the match type being used
     * @param  object $project      the project being used
     * @param  boolean $only_active only relevant for match types where a database can become inactive, determines the filter to use.
     * @return array(string)        an array of paths to databases
     */
    private function getDatabases($match_type, $media, $only_active = false) {

        $project = $media->project;

        // Set up the cache path
        $cache_path = 'pklz_cache/'.$project->id.'/'.$match_type;
        if(!is_dir(env('FPRINT_STORE').$cache_path))
            return [];

        $databases = scandir(env('FPRINT_STORE').$cache_path);

        // Drop the first two items ('..'' and '.')
        unset($databases[0]);
        unset($databases[1]);

        // Filter corpus databases if only_active is true
        if($only_active)
        {
            switch($match_type)
            {
                // Corpus only cares about buckets within 3 days of air date
                // TODO: "3" days should be an env setting
                case AudfprintFingerprinter::MATCH_CORPUS:
                    // Get the base date for this media
                    $base_time = $this->getBaseTime($media);

                    // Register the valid stems
                    $stems = [];
                    $stems[] = date('Y_m_d', $base_time);
                    for($x = 1; $x < 3; ++$x)
                    {
                        $stems[] = date('Y_m_d', strtotime(' -'.$x.' day', $base_time));
                        $stems[] = date('Y_m_d', strtotime(' +'.$x.' day', $base_time));
                    }

                    $databases = array_filter($databases, function($item) use ($stems){
                        $stem = substr($item, 0, 10);
                        if(array_search($stem, $stems) === false)
                            return false;
                        return true;
                    });

                // All others have no concept of "inactive" yet
                case AudfprintFingerprinter::MATCH_DISTRACTOR:
                case AudfprintFingerprinter::MATCH_TARGET:
                case AudfprintFingerprinter::MATCH_POTENTIAL_TARGET:
                    break;
            }
        }

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
            return AudfprintFingerprinter::DATABASE_STATUS_FULL;

        // Does this exist at all?
        if(!file_exists(env('FPRINT_STORE').$relative_database_path ))
            return AudfprintFingerprinter::DATABASE_STATUS_MISSING;

        // Looks good to me!
        return AudfprintFingerprinter::DATABASE_STATUS_GOOD;
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
        $logs = array();

        $logs[] = $this->logLine("Start: Preparing media");
        $afpt_files = $this->prepareMedia($media);
        $logs[] = $this->logLine("End:   Preparing media");

        $afpt_file = $afpt_files['full'];
        $results = array();

        // Run the match for each database
        foreach($databases as $database)
        {
            $logs[] = $this->logLine("Start: Obtaining lock for ".$database);
            // Obtain a lock on the database
            $lockfile = $this->touchFlockFile($database);
            if(flock($lockfile, LOCK_SH))
            {
                $logs[] = $this->logLine("End:   Obtaining lock for ".$database);
                // Now that we're locked, make sure the path hasn't moved
                $database = $this->resolveDatabasePath($database);

                // Run the match
                $logs[] = $this->logLine("Start: Running match in ".$database);
                $cmd = ['match', '-d', $this->resolveCachePath($database), '--find-time-range', '-x', '1000', '--match-win', '2', $this->resolveCachePath('afpt_cache/'.$afpt_file)];
                $match_logs = $this->runAudfprint($cmd);
                $logs[] = $this->logLine("End:   Running match in ".$database);

                // Release the lock
                flock($lockfile, LOCK_UN);
            }
            else
            {
                throw new \Exception("Couldn't obtain a lock for ".$database);
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

        $parsed_path = pathinfo($media_file);
        $fprint_file = $parsed_path['filename'].".afpt";

        $cmd = ['precompute', '--density', '20', '--precompdir', '/', '--wavdir', $this->resolveCachePath('media_cache'), $media_file];
        $logs = $this->runAudfprint($cmd);

        $fprint_start_path = env('FPRINT_STORE').'media_cache/'.$fprint_file;
        $fprint_end_path = env('FPRINT_STORE').'afpt_cache/'.$fprint_file;
        if(is_file($fprint_start_path))
        {
            rename($fprint_start_path, $fprint_end_path);
            return $fprint_file;
        }
        else
            throw new \Exception('Could not create a fingerprint ('.implode(" ", $cmd).'): '.$fprint_file.' - '.json_encode($logs));

        return '';
    }


    /**
     * Run a docker image
     * @param  string[] $cmd The list of commands to invoke in the docker image
     */
    private function runAudfprint($cmd) {

        // Set up the log storage
        $logs = [];

        // Are we using audfprint directly?
        if(env('AUDFPRINT_PATH') != "")
        {
            // Run audfprint directly
            $command = env('AUDFPRINT_PATH')." ".implode(" ", $cmd);
            exec($command, $output, $status_code);

            if($status_code != 0)
            {
                throw new \Exception("Attempted to run audfprint directly (".$command."), exited with status code: ".$status_code." - ".json_encode($output));
            }

            // Take the results and use them
            $logs = array_merge($logs, $output);
        }
        else
        {
            // We are using docker instead

            // Set up a guzzle connection with proper configuration
            // TODO: move timeouts to env setting
            $docker_client = \Docker\Http\DockerClient::createWithEnv();
            $docker_client->setDefaultOption('timeout', 3600);
            $docker_client->setDefaultOption('connect_timeout', 3600);

            // Create a connection with docker
            $docker = new \Docker\Docker($docker_client);

            // Create the docker container
            $container = new \Docker\Container(
                [
                    'Image' => env('DOCKER_FPRINT_IMAGE'),
                    'Cmd' => $cmd,
                    'Volumes' => [
                        '/var/audfprint' => []
                    ],
                    'HostConfig' => [
                        'Binds' => [env('FPRINT_STORE').':'.AudfprintFingerprinter::AUDFPRINT_DOCKER_PATH]
                    ]
                ]
            );

            $manager = $docker->getContainerManager();

            // Gather the logs and return them to the caller
            $manager->run($container, function($output, $type) use (&$logs) {
                // TODO: Process output more intelligently...
                $logs = array_merge($logs,explode("\n", $output));
            });

            // Clean up after yourself, it's only polite
            try
            {
                $manager->stop($container);
                $manager->remove($container, false, true);
            }
            catch(\Exception $e)
            {
                // Apparently sometimes containers don't remove, but we don't want to error when that happens.
                // TODO: figure out why container removal fails on occasion
            }
        }

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
     * Depending on the name of the file, the base time will either be pulled from the filename or the date it was created
     * @param  [type] $media [description]
     * @return [type]        [description]
     */
    private function getBaseTime($media) {
        // If the media Id is of a special magic format we can pull a date from it
        if(preg_match('/[^\_]*\_(\d\d\d\d)(\d\d)(\d\d)_.*/', $media->external_id, $matches))
        {
            $base = strtotime($matches[2].'/'.$matches[3].'/'.$matches[1]);
        }
        else
        {
            $base = strtotime($media->created_at);
        }

        return $base;
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

    /**
     * Create a line for the log file
     * @param  string $message the message to include in the log
     * @return string          the final log line
     */
    private function logLine($message) {
        return date('h:i:s')." - ".$message;
    }

    /**
     * Takes in a cache path and converts it to the path that audfprint will need
     * @param  String $path the local path to the file
     * @return String       the audfprint parameter that will resolve to the file
     */
    private function resolveCachePath($path) {

        // If we are using audfprint directly
        //  we need to provide an absolute path on the fulesystem to the cache
        if(env('AUDFPRINT_PATH') != "")
        {
            // TODO: this probably shouldn't be written in a way that relies on a specific file structure
            return env('FPRINT_STORE').$path;
        }

        // Otherwise we are using docker
        //  we need to provide the mounted path within docker
        return AudfprintFingerprinter::AUDFPRINT_DOCKER_PATH.$path;
    }
}
?>

