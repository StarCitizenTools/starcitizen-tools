#!/usr/bin/python -u
# Public domain
#
# Set of integration tests that simulate API calls made by the
# UploadWizard frontend.

import argparse
import sys
import unittest
import urllib2
import wikitools
from PIL import Image
from PIL import ImageDraw
from PIL import ImageFont
import time
import os

# This script also depends on poster: https://pypi.python.org/pypi/poster/
# To install all dependencies in your unix instance use:
#   sudo pip install -r requirements.txt

# Global wiki object to perform API calls.
wiki = None

# Global verbosity variable used inside the tests.
verbosity = 0

# Global generate new image variable used to check the --gen_new_image flag
generate_new_image = False


class TestUploadWizardAPICalls(unittest.TestCase):
    """Test API calls done by the Upload Wizard"""

    # Tests use same global variables initialized in main().
    global wiki
    global generate_new_image
    global verbosity

    def createNewImage(self):
        """Create a new image with current timestamp as text marker"""
        current_timestamp = time.time()
        filename = repr(current_timestamp) + ".png"
        ImageFont.load_default()
        image = Image.new("RGB", (125, 25))
        draw = ImageDraw.Draw(image)
        draw.text((10, 10), repr(current_timestamp), (255, 255, 255))
        # TODO(aarcos): Store in /tmp
        image.save(filename, "PNG")
        return filename

    def uploadImage(self, remote_stash_filename, final_remote_filename, local_filename):
        """Uploads image using a workflow very similar to the UploadWizard. First upload
        to the stash area, if that's successfull then upload for real.

        remote_stash_filename - Filename used when we upload to stash area
        final_remote_filename - Filename when we upload for real
        local_filename - Local filename of the image to be uploaded
        """
        # Get edit token
        params = {
            "action": "tokens",
            "type": "edit",
        }
        req = wikitools.api.APIRequest(wiki, params)
        data = req.query()
        token = data["tokens"]["edittoken"]

        self.assertTrue(len(token) > 0, "Could not get valid token")

        # Upload file to stash area
        params = {
            "action": "upload",
            "token": token,
            "stash": "1",
            "ignorewarnings": "true",
            "filename": remote_stash_filename,
            "file": open(local_filename),
        }
        req = wikitools.api.APIRequest(wiki, params, multipart=True)
        data = req.query()

        if verbosity >= 3:
            print "Response from stash upload API call:", data

        result = data["upload"]["result"]
        self.assertEqual(result, "Success", "Stash upload API call FAILED.")

        filekey = data["upload"]["filekey"]
        self.assertTrue(len(filekey) > 0, "Could not get valid filekey")

        if verbosity >= 3:
            print "filekey:", filekey

        # Upload file for real using the filekey from previous call
        params = {
            "action": "upload",
            "token": token,
            "filekey": filekey,
            "ignorewarnings": "true",
            "filename": final_remote_filename,
            "comment": "Test image uploaded via python script.",
            "text": "Image uploaded by WMF QA to monitor upload status. {{pd-ineligible}}[[Category:Test images]]",
        }
        req = wikitools.api.APIRequest(wiki, params)
        data = req.query()

        if verbosity >= 3:
            print "Response to upload API call:", data

        result = data["upload"]["result"]
        self.assertEqual(result, "Success", "Upload API call FAILED.")

        url = data["upload"]["imageinfo"]["url"]

        # Assert uploaded content same as source file !
        file_content = file(local_filename, "rb").read()
        url_content = urllib2.urlopen(url).read()

        self.assertEqual(url_content, file_content, "Uploaded content different than original !")

        if verbosity >= 0:
            print "File '%s' uploaded successfully !" % final_remote_filename

    def testUploadImageUsingWizardWorkflow(self):
        """Test that basic api calls used by the UploadWizard are working as expected"""

        remote_stash_filename = "55390test-image-rosa-mx-15x15.png"
        final_remote_filename = "Test-image-rosa-mx-15x15.png"
        local_filename = "test-image-rosa-mx-15x15.png"

        if generate_new_image:
            temp_image = self.createNewImage()
            remote_stash_filename = temp_image
            final_remote_filename = "Test-" + temp_image
            local_filename = temp_image

            if verbosity >= 3:
                print "Created temp_image:", temp_image

            try:
                self.uploadImage(remote_stash_filename, final_remote_filename, local_filename)
            finally:
                os.remove(temp_image)
        else:
            self.uploadImage(remote_stash_filename, final_remote_filename, local_filename)

    def testFileInfoAPICall(self):
        """Test file info api call used by the UploadWizard"""

        # Try to get file info from a non-existent file
        params = {
            "action": "query",
            "titles": "File:Test-image-non-existant-15x15.png",
            "prop": "info|imageinfo",
            "inprop": "protection",
            "iiprop": "url|mime|size",
            "iiurlwidth": "150",
        }
        req = wikitools.api.APIRequest(wiki, params)
        data = req.query()

        if verbosity >= 3:
            print "Response to file info API call:", data

        # Assert that no information was found about the file.
        result = data["query"]["pages"]["-1"]["missing"]
        self.assertEqual(result, "")

    def testTitleBlacklistedAPICall(self):
        """Test title blacklisted api call used by the UploadWizard"""

        # Get titleblacklist info
        params = {
            "action": "titleblacklist",
            "tbaction": "create",
            "tbtitle": "File:Test-image-rosa-mx-15x15.png",
        }
        req = wikitools.api.APIRequest(wiki, params)
        data = req.query()

        if verbosity >= 3:
            print "Response to upload API call:", data

        result = data["titleblacklist"]["result"]
        self.assertEqual(result, "ok")


def main():
    """Script that tests some API calls and workflows used by the UploadWizard.
        Example:
            $ python upload-wizard_tests.py --username some_username --password secret_password
    """

    # Global varibles that are going to be used by the tests.
    global wiki
    global verbosity
    global generate_new_image

    # Parse line arguments
    parser = argparse.ArgumentParser(description="Upload Wizard API smoke tests.")
    parser.add_argument("--api_url", default="https://commons.wikimedia.org/w/api.php",
                        help="URL of wiki API, such as http://example.org/w/api.php")
    parser.add_argument("--username", help="Username for API calls. You can also set MEDIAWIKI_USER")
    parser.add_argument("--password",
                        help="Password for API calls. You can also set MEDIAWIKI_PASSWORD " +
                        "or MEDIAWIKI_PASSWORD_VARIABLE (points to env var with password value)")
    parser.add_argument("-v", "--verbose", type=int, default=0, help="Increase output verbosity")
    parser.add_argument("--gen_new_image", action="store_true", help="Create a new image with current timestamp")
    args = parser.parse_args()

    username = args.username or os.getenv("MEDIAWIKI_USER")
    password = args.password or os.getenv("MEDIAWIKI_PASSWORD") or os.getenv(os.getenv("MEDIAWIKI_PASSWORD_VARIABLE"))

    if username is None or password is None:
        sys.stderr.write(
            "error: username and password required. Pass these values with the corresponding flags or set " +
            "the env variables: MEDIAWIKI_USER and MEDIAWIKI_PASSWORD or " +
            "MEDIAWIKI_PASSWORD_VARIABLE (points to env var with password value)\n")
        exit(1)

    # Create wikitools object
    wiki = wikitools.Wiki(args.api_url)
    generate_new_image = args.gen_new_image
    verbosity = args.verbose

    # Log in user
    wiki.login(username, password)

    if not wiki.isLoggedIn():
        sys.stderr.write("Wrong credentials, please try again.\n")
        exit(1)

    # Switch to directory of script
    abspath = os.path.abspath(__file__)
    dname = os.path.dirname(abspath)
    os.chdir(dname)

    # Run tests
    suite = unittest.TestLoader().loadTestsFromTestCase(TestUploadWizardAPICalls)
    success = unittest.TextTestRunner(verbosity=verbosity).run(suite).wasSuccessful()

    # Log out user
    wiki.logout()

    return success


if __name__ == "__main__":
    if main() is True:
        sys.exit(0)
    else:
        sys.exit(1)
