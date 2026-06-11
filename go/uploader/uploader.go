// Package uploader is the planned Go client for Upload Protocol v1.
//
// It is not yet implemented. The wire contract and conformance fixtures live in
// the repository's protocol/ directory (../../protocol). See ../README.md.
package uploader

import "errors"

// ErrNotImplemented is returned by every entry point until the Go client is built.
var ErrNotImplemented = errors.New("file-uploader: Go client not yet implemented; see protocol/SPEC.md")
