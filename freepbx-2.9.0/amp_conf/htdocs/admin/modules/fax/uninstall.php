<?php

// Delete the old code if still there
//

// Don't bother uninstalling feature codes, now module_uninstall does it

sql('DROP TABLE IF EXISTS fax_details, fax_incoming, fax_users');
?>
