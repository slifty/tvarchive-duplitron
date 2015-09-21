### Create Project

Creates and returns json data about a single new matching project.

##### URL

`/projects`

##### Method

`POST`

##### URL Params

`None`

##### Data Params

##### Required

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

Code | Content
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
