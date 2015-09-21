## Projects

### Create Project

Creates and returns json data about a single new matching project.

##### URL

`/projects`

##### Method

`POST`

##### URL Params

`None`

##### Data Params

###### Required

 * `name`: A unique name for the project.

###### Example

```json
	{
		"name" : "Political Ads"
	}
```

##### Success Response

Code | Content
--- |:---
200 | `{ id : 12, name : "Political Ads" }`

##### Error Response

Code | Content
--- |:---
400 | `{ error : "Project name already exists" }`


##### Sample Call

```javascript
	$.ajax({
		url: "/users/1",
		dataType: "json",
		data: [
			"name": "Political Ads"
		]
		type : "POST",
		success : function(r) {
			console.log(r);
		}
	});
```

----------

### Get Project

##### URL

##### Method

##### URL Params

##### Data Params

###### Required

##### Success Response

##### Sample Call

----------

### Find Project by Name

##### URL

##### Method

##### URL Params

##### Data Params

###### Required

##### Success Response

##### Sample Call

----------

## Media

###### Media Object JSON

```json
	{
		"id": 12,
		"audfprint": {
			"candidate": true,
			"corpus": false,
			"distractor": false,
			"target": false
		},
		"tasks": [],
		"project_id": 12,
		"media_path": "",
		"afpt_path": ""
	}
```


### Create Media

Creates and returns json data about a single new media project.

##### URL

`/media`

##### Method

`POST`

##### URL Params

None

##### Data Params

###### Required

 * `project_id`: The ID of the project that the media file will be compared with
 * `media_path`: A path to the media file itself.  This path can be in the following format:
 	* **Network path**: `ssh://user@example.domain/path/to/file/from/root` which will result in an rsync to retrieve the file.

###### Optional

 * `afpt_path`: A path to the pre-processed audio fingerprint file.  See the `media_path` documentation for format details.

###### Example

```json
	{
		"project_id": 12,
		"media_path": "ssh://jdoe@example.com/home/jdoe/media/rickroll.mp3",
		"afpt_path": "ssh://jdoe@example.com/home/jdoe/media/rickroll.afpt"
	}
```

##### Success Response

##### Sample Call

----------

### Get Media

##### URL

`/media/:id`

##### Method

`GET`

##### URL Params

###### Required:

* `id`: the identifier for a media record.

##### Data Params

None

##### Success Response

Code | Content
--- |:---
200 | Media Object JSON


##### Error Response

Code | Content
--- |:---
400 | `{ error : "You did not include all required fields" }`



##### Sample Call

----------

### Update Media

##### URL

##### Method

##### URL Params

##### Data Params

###### Required

##### Success Response

##### Sample Call

----------

## Tasks

###### Task Object JSON

```json
	{
		"id": 12,
		"media_id": 12,
		"task": "match",
		"status": {
			"code": 0,
			"description": "New task."
		},
		"result": {
			"code": 1,
			"data": {},
			"output": [
				"Lines of output",
				"from the process"
			]
		}
	}
```

###### Task Statuses

Code | Description
--- |:---
0 | New task
1 | Starting
2 | In Progress
3 | Finished
-1 | Failed

###### Result Codes

Code | Description
--- |:---
1 | Success
0 | Fail



###### Task Types

 * `match`: compare the media with the project fingerprints.

```json
"data": {
	"matches": [{
		"media_id": 13,
		"start_time": 15.32,
		"duration": 30,
	}],
	"segments": [{
		"segment_type": "candidate",
		"start_time": "",
		"duration": ""
	}]
}
```

 * `corpus_add`: save the media as a corpus item.
 * `corpus_remove`: remove the media from the corpus.
 * `candidate_add`: add the media as a candidate item.
 * `candidate_remove`: remove the media from the candidates.
 * `distractor_add`: add the media as a distractor item.
 * `distractor_remove`: remove the media from the distractors.
 * `target_add`: add the media as a target item.
 * `target_remove`: remove the media from the targets.

####### Match results


### Create Task

Once media is registered in the system it can be processed using fingerprinting and matching algorithms.  These activities can be intense, so they are handled by a task queue (instead of being synchronous).

##### URL

`/tasks`

##### Method

`POST`

##### URL Params

None

##### Data Params

###### Required

 * `media_id`: The ID of the media file that this task will be invoked on.
 * `task`: The task to be performed (see "Task Types")

###### Optional

None

###### Example

```json
	{
		"media_id": 12,
		"task": "match"
	}
```

##### Success Response

##### Sample Call

----------

### Get Media Task

##### URL

`/tasks/:id`

##### Method

`POST`

##### URL Params

##### Data Params

###### Required

##### Success Response

##### Sample Call
