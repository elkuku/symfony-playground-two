#!/bin/bash
#
# Functions for install/start/stop scripts
#
# License: WTFPL
# Author: Nikolai Plath
#

function checkIsInstalled {
  if ! command -v "$1" &> /dev/null
  then
      echo "❌ ERROR: Command '$1' could not be found on your system :("
      exit 1
  fi
}

function checkDockerCompose {
  checkIsInstalled docker
  if ! docker compose version &> /dev/null
  then
      echo "❌ ERROR: 'docker compose' (Compose v2) is not available on your system :("
      exit 1
  fi
}
