# Imageshare

## API

Imageshare exposes a read-only JSON API implemented using [the json-api spec](https://jsonapi.org/).
Available details are documented below; specific records can be queried via their ID, e.g. `/json-api/types/226` to query a single type.

### Types: `/json-api/types`
```json
{
    "data": [
        {
            "type": "type",
            "id": "226",
            "attributes": {
                "name": "2.5D Tactile Graphic",
                "thumbnail": "[url]"
            }
        },
        {
            "type": "type",
            "id": "227",
            "attributes": {
                "name": "3D Model",
                "thumbnail": "[url]"
            }
        },
    ]
}
```

### Subjects: `/json-api/subjects`
```json
{
    "data": [
        {
            "type": "subject",
            "id": "285",
            "attributes": {
                "name": "Space Sciences"
            },
            "relationships": {
                "parent": {
                    "links": {
                        "self": "https://imageshare.benetech.org/json-api/subjects/285/relationships/parent",
                        "related": "https://imageshare.benetech.org/json-api/subjects/285/parent"
                    },
                    "data": {
                        "type": "subject",
                        "id": "236"
                    }
                }
            }
        },
        {
            "type": "subject",
            "id": "288",
            "attributes": {
                "name": "Careers"
            },
            "relationships": {
                "children": {
                    "links": {
                        "self": "https://imageshare.benetech.org/json-api/subjects/288/relationships/children",
                        "related": "https://imageshare.benetech.org/json-api/subjects/288/children"
                    },
                    "data": [
                        {
                            "type": "subject",
                            "id": "289"
                        },
                        {
                            "type": "subject",
                            "id": "338"
                        },
                        {
                            "type": "subject",
                            "id": "339"
                        },
                        {
                            "type": "subject",
                            "id": "503"
                        },
                        {
                            "type": "subject",
                            "id": "290"
                        }
                    ]
                }
            }
        },
    ]
}
```

### Accommodations: `/json-api/accommodations`

```json
{
    "data": [
        {
            "type": "resource_accommodation",
            "id": "171",
            "attributes": {
                "name": "Auditory"
            }
        },
        {
            "type": "resource_accommodation",
            "id": "179",
            "attributes": {
                "name": "Braille"
            }
        },
        {
            "type": "resource_accommodation",
            "id": "172",
            "attributes": {
                "name": "Closed Captioning"
            }
        },
        {
            "type": "resource_accommodation",
            "id": "176",
            "attributes": {
                "name": "Cognitive"
            }
        },
    ]
}
```

### Sources: `/json-api/sources`

```json
{
    "data": [
        {
            "type": "source",
            "id": "aph",
            "attributes": {
                "name": "APH"
            }
        },
        {
            "type": "source",
            "id": "benetech",
            "attributes": {
                "name": "Benetech"
            }
        },
        {
            "type": "source",
            "id": "dcmp",
            "attributes": {
                "name": "DCMP"
            }
        },
    ]
}
```

### Keywords: `/json-api/keywords`
```
{
    "data": [
        {
            "type": "keyword",
            "id": 2773,
            "attributes": {
                "name": "3d"
            }
        },
        {
            "type": "keyword",
            "id": 2724,
            "attributes": {
                "name": "3d print"
            }
        },
        {
            "type": "keyword",
            "id": 2807,
            "attributes": {
                "name": "3d technology"
            }
        },
    ]
}
```

### Collections: `/json-api/collections`

```json
{
    "data": [
        {
            "type": "collection",
            "id": "214",
            "attributes": {
                "status": "publish",
                "title": "Biology",
                "description": "Biology related concepts",
                "featured": false,
                "contributor": "Benetech",
                "thumbnail": "[url]",
                "size": 59
            },
            "relationships": {
                "members": {
                    "links": {
                        "self": "https://imageshare.benetech.org/json-api/collections/214/relationships/members",
                        "related": "https://imageshare.benetech.org/json-api/collections/214/members"
                    },
                    "data": [
                        {
                            "type": "resource",
                            "id": "11281"
                        },
                        {
                            "type": "resource",
                            "id": "11283"
                        },
                        {
                            "type": "resource",
                            "id": "11285"
                        },
                        {
                            "type": "resource",
                            "id": "11287"
                        },
                        {
                            "type": "resource",
                            "id": "15426"
                        },
                    ]
                }
            }
        },
    ]
}
```

### Resources: `/json-api/resources`

```json
{
    "data": [
        {
            "type": "resource",
            "status": "publish",
            "id": "9784",
            "attributes": {
                "title": "Procaryotic Cell",
                "description": "Illistration of the Procaryotic Cell",
                "source": "",
                "tags": [],
                "files": 0
            },
            "relationships": {
                "subject": {
                    "links": {
                        "self": "https://imageshare.benetech.org/json-api/resources/9784/relationships/subject",
                        "related": "https://imageshare.benetech.org/json-api/resources/9784/subject"
                    },
                    "data": {
                        "type": "subject",
                        "id": "283"
                    }
                }
            }
        },
        {
            "type": "resource",
            "status": "publish",
            "id": "9752",
            "attributes": {
                "title": "Atoms",
                "description": "What is an atom? It is the smallest particle of an element, and everything is made up of atoms. They consist of three basic particles: protons, electrons, and neutrons. The scientific community has experienced significant breakthroughs which have contributed to the understanding of atoms. Other topics covered include atomic number, atomic mass, Bohr model, electron cloud, and isotope.",
                "source": "DCMP",
                "tags": [
                    "chemistry",
                    "electrons",
                    "neutrons",
                    "physics",
                    "protons",
                    "units of measurement"
                ],
                "files": 1
            },
            "relationships": {
                "files": {
                    "links": {
                        "self": "https://imageshare.benetech.org/json-api/resources/9752/relationships/files",
                        "related": "https://imageshare.benetech.org/json-api/resources/9752/files"
                    },
                    "data": {
                        "type": "resource_file",
                        "id": "9753"
                    }
                },
                "subject": {
                    "links": {
                        "self": "https://imageshare.benetech.org/json-api/resources/9752/relationships/subject",
                        "related": "https://imageshare.benetech.org/json-api/resources/9752/subject"
                    },
                    "data": {
                        "type": "subject",
                        "id": "284"
                    }
                },
                "collections": {
                    "links": {
                        "self": "https://imageshare.benetech.org/json-api/resources/9752/relationships/collections",
                        "related": "https://imageshare.benetech.org/json-api/resources/9752/collections"
                    },
                    "data": {
                        "type": "collection",
                        "id": "16820"
                    }
                }
            }
        },
    ]
}
```

#### Resource search

Resources can be queried for specific details. The following parameters are supported:

* `query` - A search query, like "fish".
* `type` - A file type, like "Image".
* `format` - A file format, like "JPG"
* `source` - A file source, like "Benetech"

An example query could look like: `/json-api/resources/filter/?query=fish&type=Image&format=JPG`
