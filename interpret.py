#  interpret of IPPcode20 from XML file
#  author : Vojtech Coupek - xcoupe01

import sys
import re

# variables
debug = True  # < print debug prints


# functions

def d_print(message):
    if debug:
        print(message)


# main
# dealing with args
sys.argv.remove('interpret.py')
srcFile = ''
inFile = ''
if (len(sys.argv) == 1) & ((sys.argv[0] == "--help") | (sys.argv[0] == '-h')):
    print('\n'
          ' Interpret of XML representation of IPPcode20\n'
          ' Options: \n'
          ' "--help" or "-h    to print help info\n'
          ' "--source=[file]"  to set file that the XML will be loaded from    **\n'
          ' "--input=[file]"   to set file that the input will be loaded from  **\n'
          ' ** at least one of those (last two) must be set. The unset on will be read from STDIN\n')
else:
    for i in range(len(sys.argv)):
        if (sys.argv[i] == '--help') | (sys.argv[i] == '-h'):
            d_print('ARG - err bad args')
            exit(10)
        elif re.match(r'^--source=([\S]+)$', sys.argv[i]) is not None:
            srcFile = re.match(r'^--source=([\S]+)$', sys.argv[i])
            d_print('ARG - source file set to [' + srcFile[1] + ']')
        elif re.match(r'^--input=([\S]+)$', sys.argv[i]) is not None:
            inFile = re.match(r'^--input=([\S]+)$', sys.argv[i])
            d_print('ARG - input file set to [' + inFile[1] + ']')
        else:
            d_print("Unknown arg")
srcHandle = sys.stdin
inHandle = sys.stdin
if (srcFile == '') & (inFile == ''):
    d_print("ARG - err at least one of --source or --input must be set")
    exit(11)
elif srcFile == '':
    inHandle = open(inFile[1], 'r')
    d_print('ARG - source set to [STDIN]')
elif inFile == '':
    srcHandle = open(srcFile[1], 'r')
    d_print('ARG - input set to [STDIN]')
else:
    srcHandle = open(srcFile[1], 'r')
    inHandle = open(inFile[1], 'r')

# temp code to test input and source

d_print('READ - src file')

for line in srcHandle:
    print(line)

d_print('READ -in file')

for line in inHandle:
    print(line)

# it works yay

srcHandle.close()
inHandle.close()
