## Create Project

	Creates and returns json data about a single new matching project.

### URL

	`/projects`

### Method

	`POST`

### URL Params

	`None`

### Data Params

	**Required:**

	 * `name=[string]` - A unique name for the project.

	 Example:

	 ````
		{
			name : [string]
		}
	 ````

### Success Response

	* **Code:** 200 <br />
		**Content:** `{ id : 12, name : "Political Ads" }`

### Error Response

	* **Code:** 400 BAD REQUEST <br />
		**Content:** `{ error : "Project name already exists" }`


### Sample Call

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
