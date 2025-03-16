# How to access the Rest API

Below are various examples on how to access the Rest API. Some of them
start by calling the `login` endpoint which creates a cookie based
session which is stored in a local file named `cookies.txt`.
The authentication is done with the user `admin`. You may use any other
user as well.

You may as well pass `-H Authorization: <api key>` instead of `-b cookies.txt`
to `curl` after setting the api key in the configuration of your SeedDMS.
Of course, in that case you will not need the initial call of the `login`
endpoint.

The examples often use the `jq` programm for formating the returned
json data.

## Initial test

The `echo` endpoint does not require any authentication.

```
#!/bin/sh
BASEURL="https://your-domain/"

curl --silent -X GET ${BASEURL}restapi/index.php/echo/test | jq '.'

```

## Getting list of users

```
#!/bin/sh
BASEURL="https://your-domain/"

curl --silent -F "user=admin" -F "pass=admin" -b cookies.txt -c cookies.txt ${BASEURL}restapi/index.php/login | jq

curl --silent -b cookies.txt -X GET "${BASEURL}restapi/index.php/users" | jq '.'
```

## Getting meta data of a folder

```
#!/bin/sh
BASEURL="https://your-domain/"

curl --silent -H "Authorization: <api key>" -X GET "${BASEURL}restapi/index.php/folder/1" | jq '.'
```
## Notes

Make sure to encode the data properly when using restapi functions which uses
put. If you use curl with PHP, then encode the data as the following

  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

