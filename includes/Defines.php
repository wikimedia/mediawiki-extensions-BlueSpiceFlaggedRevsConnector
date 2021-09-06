<?php

/*
 * Fix WMF CI issues on `master` branch.
 * Situation Septembert 2021:
 * - WMF has removed `FR_INCLUDES_FREEZE` from `Extension:FlaggedRevs` (`master`) due to T277883
 * - `Extension:BlueSpiceFLaggedRevsConnector` (`master`) still uses this in some parts of the code
 * - WMF CI on `master` breaks
 *
 * For the next major release of BlueSpice, we will fins a solution. In the meantime, we fix WMF CI
 * with this. See also ERM24524.
 */
define( 'FR_INCLUDES_FREEZE', 1 );
