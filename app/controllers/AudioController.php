<?php

    /**
     * This file enables manipulation of an audio file into segments
     *
     * @author Dan Schultz <dan.schultz@archive.org>
	 *
	 * Copyright(c)2008-2015 Internet Archive. Software license AGPL version 3.
	 * 
	 *  This file is part of The Political TV Archive's Ad Detection Suite.
	 *  
	 *  The Political TV Archive's Ad Detection Suite is free software: you can redistribute it and/or modify
	 *  it under the terms of the GNU Affero General Public License as published by
	 *  the Free Software Foundation, either version 3 of the License, or
	 *  (at your option) any later version.
	 *  
	 *  The Political TV Archive's Ad Detection Suite is distributed in the hope that it will be useful,
	 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
	 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	 *  GNU Affero General Public License for more details.
	 *  
	 *  You should have received a copy of the GNU Affero General Public License
	 *  along with he Political TV Archive's Ad Detection Suite.  If not, see <http://www.gnu.org/licenses/>.
	 *  
	 */

	class AudioController {

		const SLICE_DURATION = 10;

		// Add a new audio file to the system
		public function addMedia($file_path) {

			// Load the file
			$parser = new PHPVideoToolkit\MediaParser();
			$audio_data = $parser->getFileInformation($file_path);
		
			// Split into pieces
			$slice_duration = self::SLICE_DURATION;
			$duration = $audio_data['duration']->total_seconds;
			$slices = floor($duration / $slice_duration); // Note: this may mean we don't test the final few seconds of a video
			
			// Get the filename we're working with
			$base = basename($file_path, ".mp3");

			// Split the file into 15 second chunks
			for($cursor = 0; $cursor < $slices; $cursor++) {
				$audio  = new PHPVideoToolkit\Audio($file_path, null, null, false);
				$start = new PHPVideoToolkit\Timecode($cursor * $slice_duration);
				$end = new PHPVideoToolkit\Timecode(($cursor + 1) * $slice_duration);
				$command = $audio->extractSegment($start, $end);
				$process = $command->save('output/'.$base.'_'.$start->total_seconds.'_'.$end->total_seconds.'.mp3', null, true);
			}

			// 
		}
	}

?>