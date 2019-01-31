# Quivers for Drupal 8.x + Commerce 2.x

This module has Custom Drupal Order Workflows with Quivers-Tax Plugin, and Admin Returns.

## Pre-Installation

Install the project dependencies.


## Post-Installation

After installing the module, 

Step 1: Go to `Commerce > Configuration > Order Types > Edit > Workflow`. You will see a list, you must pick 'Quivers Integration'. 

Step 2: Go to `Commerce > Configuration > Quivers Integration Settings`. Select Quivers Sandbox Enabled as 'Yes' for development mode, and 'No' for Live Mode. Enter Quivers Marketplace Id's for the selected mode. Enter Claiming Groups, Quivers API Key and Business RefId.

Step 3: Go to `Commerce > Configuration > Tax types > Edit > + Add tax type`. Name it 'quiverstax' and Select 'Quiverstax' in 'Plugin' dropdown.

## Note

Go to `Commerce > Configuration > Checkout flows > Shipping > Edit`.
Make sure Transaction mode for 'Payment process' is set to 'Authorize only'.

## To make a .tar.gz module

Clone the repo, `rm -rf <Project repo>/.git`, `mv quivers.plugin.drupal quivers`, `tar -czvf quivers.tar.gz quivers`
