<?php

define('VERSION', '0.0.4.dev1');

# Endianess for storing numbers
define('ENDIAN', false); // false: little, true: big, null: machine byte order

# Bytes used for storing references to pages
# Can address 16 TB of memory with 4 KB pages
define('PAGE_REFERENCE_BYTES', 4);

# Bytes used for storing the type of the node in page header
define('NODE_TYPE_BYTES', 1);

# Bytes used for storing the length of the page payload in page header
define('USED_PAGE_LENGTH_BYTES', 3);

# Bytes used for storing the length of the key or value payload in record
# header. Limits the maximum length of a key or value to 64 KB.
define('USED_KEY_LENGTH_BYTES', 2);
define('USED_VALUE_LENGTH_BYTES', 2);

# Max 256 types of frames
define('FRAME_TYPE_BYTES', 1);

# Bytes used for storing general purpose integers like file metadata
define('OTHERS_BYTES', 4);