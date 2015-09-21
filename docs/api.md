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

 * name: A unique name for the project.

```json
	{
		"name" : [string]
	}
```

##### Success Response

Code | Content
--- | ---
200 | `{ id : 12, name : "Political Ads" }`

##### Error Response

Code | :Content
--- | ---
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

##### Required

##### Success Response

##### Sample Call

----------

### Find Project by Name

##### URL

##### Method

##### URL Params

##### Data Params

##### Required

##### Success Response

##### Sample Call

----------

## Media
### Create Media

Creates and returns json data about a single new media project.

##### URL

`/media`

##### Method

`POST`

##### URL Params

##### Data Params

##### Required

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
--- | ---
200 | ```json
	{
		"id": 12,
		"audfprint": {
			"candidate": true,
			"corpus": false,
			"distractor": false,
			"match": false
		}
		"jobs": [],
		"project_id": 12,
		"media_rsync_path": "",
		"audfprint_rsync_path": ""
	}
```

##### Error Response

Code | :Content
--- | ---
404 | `{ error : "The record could not be found" }`



##### Sample Call

----------

### Update Media

##### URL

##### Method

##### URL Params

##### Data Params

##### Required

##### Success Response

##### Sample Call

----------

## Media Tasks

### Create Media Task

Once media is registered in the system it can be processed using fingerprinting and matching algorithms.  These activities can be intense, so they are handled by a task queue (instead of being synchronous).

##### URL

`/tasks`

##### Method

`POST`

##### URL Params

##### Data Params

##### Required

##### Success Response

##### Sample Call

----------

### Get Media Task

Once media is registered in the system it can be processed using fingerprinting and matching algorithms.  These activities can be intense, so they are handled by a task queue (instead of being synchronous).

##### URL

`/tasks/:id`

##### Method

`POST`

##### URL Params

##### Data Params

##### Required

##### Success Response

##### Sample Call

----------

### Get Media Task

Once media is registered in the system it can be processed using fingerprinting and matching algorithms.  These activities can be intense, so they are handled by a task queue (instead of being synchronous).

##### URL

`/tasks/:id`

##### Method

`POST`

##### URL Params

##### Data Params

##### Required

##### Success Response

##### Sample Call
