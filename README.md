# Simple XML import for OJS

This is an alternative OJS Native XML importer plugin for OJS.

Essentially it does a more relaxed version of what OJS natively implements, using it's file format,
but easier to add extension points in and add custom functionality. Also we do not validate
against an incredibly strict XSD.

Effectively so long as your XML roughly looks like an OJS file, it should be able to figure it out
and will also prefer to update existing articles should they exist so you can re-run this tool
if you need to make changes to your OJS files.

It is a little rough around the edges, but it does work.

## OICC Press in collaboration with Invisible Dragon

![OICC Press in Collaboration with Invisible Dragon](https://images.invisibledragonltd.com/oicc-collab.png)

This project is brought to you by [Invisible Dragon](https://invisibledragonltd.com/ojs/) in collaboration with
[OICC Press](https://oiccpress.com/)

## Copyright

Copyright 2025 OICC Press

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
