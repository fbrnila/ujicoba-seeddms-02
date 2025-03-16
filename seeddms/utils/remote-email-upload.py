#! /usr/bin/env python3

# This script can be used by mutt to upload a complete email message
# in eml format into SeedDMS. Just add a line
#
# macro index,pager S "| remote-email-upload.py<enter>"
#
# to your .muttrc. It will allow by pressing 'S' in index or pager mode
# to upload the complete email into seeddms.

import sys
import os
import time
import argparse
import getopt
import locale
from dialog import Dialog
import tomllib
import requests
import re
import email
from email.header import Header, decode_header, make_header
from  dateutil.parser import *

locale.setlocale(locale.LC_ALL, '')

def upload_chunk(message, gauge):
    prevnl = -1
    while True:
        nextnl = prevnl + (1 << 13)
        if nextnl > len(message):
            d.gauge_update(100, 'Successfully uploaded!', True)
            yield message[prevnl + 1:len(message)]
            break;
        else:
            d.gauge_update(int(nextnl*100/len(message)))
            yield message[prevnl + 1:nextnl]
        prevnl = nextnl-1

if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description='''
            This programms needs a configuration file named .seeddms-upload.conf
            located in your home directory or specified by --config.
            The configuration file must be in toml format an may contain any
            number of sections, each setting the parameters to access a seeddms
            installation.
            
            The following variables can be set per section in ~/.seeddms-upload.conf
              baseurl: url of restapi service
              username: name of user to be used
              password: password of user to be used. If not set, it will be asked.
              targetfolder: array of folder ids where the file can be uploaded'''
        )
    parser.add_argument("-c", "--config", default=os.path.expanduser("~")+"/.seeddms-upload.conf", help="read this config file")
    parser.add_argument("-s", "--section", help="take this section from the config file")
    args= parser.parse_args()

    d = Dialog(dialog="dialog")
    d.set_background_title("Upload mail into SeedDMS")

    # First check if config file can be read
    if args.config:
        try:
            f = open(args.config, "rb")
            config = tomllib.load(f)
        except FileNotFoundError:
            d.msgbox("Could not open config file " + args.config)
            sys.exit(1)
    else:
        d.msgbox("No config file given")
        sys.exit(1)

    # If the configuration file has just one section then use it
    # even if a different section was set on the command line
    if len(config) == 1:
        section = list(config.keys())[0]
        sectionname = config.keys()[0]
    else:
        if args.section:
            if args.section not in config.keys():
                d.msgbox("Section " + args.section + " not found")
                sys.exit(1)
            sectionname = args.section
        else:
            server = []
            for x in config.keys():
                server.append((x, x))
            code, sectionname = d.menu("Select server", choices=server)
            if code != d.OK:
                d.msgbox("No server selected")
                sys.exit(1)

    section = config.get(sectionname)

    baseurl = section.get('baseurl')
    username = section.get('username')
    password = section.get('password')
    targetfolder = section.get('targetfolder')

    if baseurl == '':
        d.msgbox("No base URL set")
        sys.exit(1)
    if targetfolder == '':
        d.msgbox("No target folder set")
        sys.exit(1)
    if password == '':
        code, password = d.passwordbox("Password")

    r = requests.post(baseurl + '/login', {'user': username, 'pass': password})
    try:
        j = r.json()
    except:
        d.msgbox("Could not decode json")
        sys.exit(1)
    if j['success'] == False:
        d.msgbox(j['message'])
        sys.exit(1)
    cookies = r.cookies

    folderids = targetfolder
    if isinstance(folderids, int):
        targetfolderid = folderids
    elif len(folderids) > 1:
        folders = []
        for x in folderids:
            r = requests.get(baseurl + '/folder/' + str(x), cookies=cookies)
            j = r.json()
            if j['success'] == True:
                folders.append((str(x), j['data']['name']))
        if len(folders) > 1:
            print(folders)
            code, folderid = d.menu("Select folder from " + sectionname, choices=folders)
            if code == d.OK:
                targetfolderid = folderid
            else:
                sys.exit(1)
        else:
            targetfolderid = 0
    else:
        targetfolderid = folderids[0]

    r = requests.get(baseurl + '/folder/' + str(targetfolderid), cookies=cookies)
    j = r.json()
    if j['success'] == False:
        d.msgbox("Could not get target folder")
        sys.exit(1)

    # Read message from stdin and check if it has as date and subject
    message = sys.stdin.read()
    msg = email.message_from_string(message)
    if msg.get('date') == None or msg.get('subject') == None :
        d.msgbox("Input does not seem to be an email")
        sys.exit(1)

    dobj = parse(msg['date'])
    subject = make_header(decode_header(msg.get('subject')))
    msgid = msg.get('Message-ID')
    fromaddress = make_header(decode_header(msg.get('From')))

#    Sending the data with upload_chunk() doesn't work if the message is divided
#    in several chunks
#    d.gauge_start("Uploading email ...")
#    r = requests.put(baseurl + '/folder/' + str(targetfolderid) + '/document', data=upload_chunk(message, d), params={'name':msg['subject']+'-'+dobj.strftime('%Y-%m-%d'), 'origfilename': msg['subject']+'-'+dobj.strftime('%Y-%m-%d')+'.eml'}, cookies=cookies)
#    d.gauge_stop()
    r = requests.put(baseurl + '/folder/' + str(targetfolderid) + '/document', data=message, params={'name':str(subject)+'-'+dobj.strftime('%Y-%m-%d'), 'comment':str(fromaddress), 'origfilename': str(msgid)+'-'+dobj.strftime('%Y-%m-%d')+'.eml'}, cookies=cookies)

    j = r.json()
    if j['success'] == False:
        d.msgbox("Could not upload email. " + j['message'])
        sys.exit(1)
    else:
        d.infobox("File successfully uploaded with id=" + str(j['data']['id']))

    sys.exit(0)
