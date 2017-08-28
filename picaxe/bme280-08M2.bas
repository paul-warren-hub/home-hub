' BME280-08M2.Bas - PICAXE-08M2
'
' Serial Interface to BME280 SPI
'
'
'        PICAXE-08M2	        		BME280
'
' SCLK, Out C.4 (pin 3) --------->    SCL (term 3)
' MOSI, Out C.2 (pin 5) --------->    SDA (term 4)
' SS,   Out C.1 (pin 6) --------->    CSB (term 5)
' MISO, In  C.3 (pin 4)  <------      SDO (term 6)
'
'Protocol
'
' $D0 208 - Get Chip ID    [1]	A  >>  _
' $88 136 - Get Eprom Data [24]	B  >>  /
' $A1 161 - Get Eprom Data [1]	C  >>  ^
' $E1 225 - Get Eprom Data [7]	E  >>  ]
' 224 - Soft Reset     [3]	F  >>  .
' 242 - Initialise     [3] 	J  >>  -
' $F7 247 - Get Env data   [9]	K  >>  Z
'
#no_data 'Prevent data from being downloaded to PICAXE.


   Symbol Address = B0
   Symbol DataLen = B1
   Symbol AddrIdx = B2
   
   Symbol RegData = B3
   
   Symbol ShftOut = B4
   Symbol ShftIn = B5
   Symbol ShftMsk = B6
   Symbol ShftIdx = B7
   
   Symbol DataIn = B8

   Symbol SS = C.1
   Symbol SCLK = C.4
   Symbol MISO = pin3
   Symbol MOSI = C.2

   'Assign Inputs and Outputs
   Output C.1, C.2, C.4
   Input C.3
   
   'Init Serial
   disconnect
   setfreq m4 'Default
   hsersetup B4800_4,%000
   'bit0 - background receive serial data to the scratchpad (not M2 parts)
   'bit1 - invert serial output data (0 = ?T?, 1 = ?N?)
   'bit 2 - invert serial input data (0 = ?T?, 1 = ?N?)
   
Timeout:

   hserout 0,("timeout")
   
Top:
   
   SerRxD [20000, Timeout], b12          'Read serial in port and wait for cmd byte
   
   If b12 = "_" Then
   
	   'Read Chip ID
	   Address = $D0			' $D0 for chip id 208 dec.
	   DataLen = 1
	   GoSub ReadData
	   
   ElseIf b12 = "/" Then

	   'Read Eprom Data 1
	   Address = $88			'136 dec.
	   DataLen = 24			'24 bytes
	   GoSub ReadData
   
   ElseIf b12 = "^" Then

	   'Read Eprom Data 2
	   Address = $A1			'161 dec.
	   DataLen = 1			'1 byte
	   GoSub ReadData
   
   ElseIf b12 = "]" Then

	   'Read Eprom Data 3
	   Address = $E1			'225 dec.
	   DataLen = 7			'7 bytes
	   GoSub ReadData
   
   ElseIf b12 = "." Then
	   
	'Write soft reset to Control $E0
   	Address = $60	'224 dec.
   	RegData = $B6
   	Gosub WriteBytes  
	hserout 0, (13, 10)	

   ElseIf b12 = "-" Then
	   
     '242 dec
     Gosub Sample
     hserout 0, (13, 10)
     
   ElseIf b12 = "Z" Then
   
     'Read Env Data
     Gosub Sample 
     Address = $F7		'247 dec.
     DataLen = 8		'8 bytes
     GoSub ReadData
     
   Else
	hserout 0,(b12, "error")
   EndIf

Goto Top

Sample:
     'Write enable to Humidity Control $F2
     'In SPI mode, only 7 bits of the register addresses are used; the MSB of register address is not
     'used and replaced by a read/write bit (RW = ?0? for write and RW = ?1? for read).
     'Example: address 0xF7 is accessed by using SPI register address 0x77. For write access, the
     'byte 0x77 is transferred, for read access, the byte 0xF7 is transferred. 
	
     Address = $72 'Write to F2 242 dec
     RegData = $01
     Gosub WriteBytes   

     'Write Mode to Control $F4
     Address = $74 'Write to F4
     RegData = $25
     Gosub WriteBytes 
     Return
   
WriteBytes:
   Low SCLK				' Send Clk Low
   Low SS				' Send Slave Select Low
   ShftOut = Address
   GoSub SPI_IO			' Tx/Rx
   
   ShftOut = RegData			' Most Significant 8bits of 16bit Channel
   GoSub SPI_IO			' Tx/Rx
   
   High SS				' Send Slave Select High  
   Return

ReadData:
   'Read Address for L bytes
   Low SCLK				' Send Clk Low
   Low SS				' Send Slave Select Low
   For AddrIdx = 0 to DataLen		'AddrIdx loops byte 0 to DataLen
   	   Address = Address + AddrIdx	
   	   GoSub ReadByte
   Next
   High SS				' Send Slave Select High  

   SerTxD (13, 10)
   Return
   
ReadByte:
   ShftOut = Address		' Address
   GoSub SPI_IO			' Tx/Rx
   If AddrIdx > 0 Then
	hserout 0, (#DataIn, 32)
   EndIf
   Return

SPI_IO:						'Shift Data Out and read Data back in

   ShftIn = 0					'Clear Result
   For ShftIdx = 0 to 7				'N loops bit 0 to 7
	
	If ShftOut > 127 then
		High MOSI				'Master Out High
	Else
		low MOSI				'Master Out Low
	EndIf
	   
      High SCLK				' Send Clk High
      ShftIn = ShftIn * 2 + MISO	' Shift Input left, Read Master-In (1 or 0) and add on end 
      Low SCLK				' Send Clk Low
	ShftOut = ShftOut * 2		' Shift Output Left

   Next					'Next Bit
   DataIn = ShftIn
   Return


