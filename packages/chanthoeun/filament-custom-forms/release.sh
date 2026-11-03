#!/bin/bash

# Simple release script for Filament Custom Forms

if [ -z "$1" ]; then
    echo "Usage: ./release.sh vX.Y.Z"
    exit 1
fi

VERSION=$1

echo "Releasing version $VERSION..."

# check if version is in changelog
if ! grep -q "$VERSION" CHANGELOG.md; then
    echo "Error: Version $VERSION not found in CHANGELOG.md"
    exit 1
fi

git add .
git commit -m "chore: release $VERSION"
git tag "$VERSION"
git push origin main
git push origin "$VERSION"

echo "Successfully released $VERSION"
