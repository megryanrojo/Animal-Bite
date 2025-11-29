-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 29, 2025 at 11:03 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `animal_bite`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `adminId` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`adminId`, `name`, `email`, `password`) VALUES
(1, 'Meg Ryan Rojo', 'megrojo76@gmail.com', '$2y$10$Xrv0t3xETUiIxDBq21YljuCHzTtcXJhsZVH65SpyVNZ//SdpglP6.');

-- --------------------------------------------------------

--
-- Table structure for table `barangay_coordinates`
--

CREATE TABLE `barangay_coordinates` (
  `id` int(11) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangay_coordinates`
--

INSERT INTO `barangay_coordinates` (`id`, `barangay`, `latitude`, `longitude`) VALUES
(6, 'Bubog', 10.7391076, 122.9734084),
(7, 'Cabatangan', 10.6703959, 123.1042339),
(8, 'Zone 4-A (Pob.)', 10.7402669, 122.9657439),
(9, 'Zone 4 (Pob.)', 10.7413916, 122.9655924),
(10, 'Concepcion', 10.6917673, 123.0598706),
(11, 'Dos Hermanas', 10.7433787, 123.0394487),
(12, 'Efigenio Lizares', 10.7474897, 123.0059292),
(13, 'Zone 7 (Pob.)', 10.7352619, 122.9640742),
(14, 'Zone 14-B (Pob.)', 10.7360979, 122.9673086),
(15, 'Zone 12-A (Pob.)', 10.7406419, 122.9767205),
(16, 'Zone 10 (Pob.)', 10.7322738, 122.9747986),
(17, 'Zone 5 (Pob.)', 10.7395402, 122.9644956),
(18, 'Zone 16 (Pob.)', 10.7494997, 122.9672779),
(19, 'Matab-ang', 10.7179803, 123.0163745),
(20, 'Zone 9 (Pob.)', 10.7353899, 122.9676643),
(21, 'Zone 6 (Pob.)', 10.7380045, 122.9647299),
(22, 'San Fernando', 10.7091894, 123.0688640),
(23, 'Zone 15 (Pob.)', 10.7323830, 122.9640094),
(24, 'Zone 14-A (Pob.)', 10.7315478, 122.9679585),
(25, 'Zone 11 (Pob.)', 10.7335797, 122.9675229),
(26, 'Zone 8 (Pob.)', 10.7341449, 122.9663017),
(27, 'Zone 12 (Pob.)', 10.7374706, 122.9704746),
(28, 'Zone 1 (Pob.)', 10.7454076, 122.9713217),
(29, 'Zone 2 (Pob.)', 10.7439955, 122.9681055),
(30, 'Zone 3 (Pob.)', 10.7441353, 122.9626415),
(31, 'Katilingban', 10.6962026, 123.1313892);

-- --------------------------------------------------------

--
-- Table structure for table `classifications`
--

CREATE TABLE `classifications` (
  `classificationID` int(11) NOT NULL,
  `classificationName` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `severityLevel` enum('Low','Medium','High') NOT NULL,
  `animalType` varchar(50) DEFAULT NULL,
  `biteCondition` text DEFAULT NULL,
  `recommendedAction` text DEFAULT NULL,
  `colorCode` varchar(20) DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classifications`
--

INSERT INTO `classifications` (`classificationID`, `classificationName`, `description`, `severityLevel`, `animalType`, `biteCondition`, `recommendedAction`, `colorCode`, `isActive`, `created_at`, `updated_at`) VALUES
(1, 'Minor Scratch', 'Superficial scratch with minimal skin breakage', 'Low', 'Any', 'Superficial wound, no bleeding or minimal bleeding', 'Clean wound with soap and water, monitor for infection', '#4caf50', 1, '2025-05-14 23:18:52', '2025-05-14 23:18:52'),
(2, 'Category I Exposure', 'Touching or feeding animals, licks on intact skin', 'Low', 'Any', 'No skin breakage', 'Wash exposed area thoroughly, no vaccine required if animal is healthy', '#8bc34a', 1, '2025-05-14 23:18:52', '2025-05-14 23:18:52'),
(3, 'Category II Exposure', 'Nibbling of uncovered skin, minor scratches or abrasions without bleeding', 'Medium', 'Any', 'Minor wounds without bleeding', 'Immediate wound washing and post-exposure prophylaxis', '#ffc107', 1, '2025-05-14 23:18:52', '2025-05-14 23:18:52'),
(4, 'Category III Exposure', 'Single or multiple transdermal bites or scratches, contamination of mucous membrane with saliva', 'High', 'Any', 'Deep puncture wounds, bleeding wounds', 'Immediate wound washing, rabies immunoglobulin and vaccine', '#f44336', 1, '2025-05-14 23:18:52', '2025-05-14 23:18:52'),
(5, 'Severe Dog Bite', 'Deep bite from dog with significant tissue damage', 'High', 'Dog', 'Deep puncture wounds, tissue damage, heavy bleeding', 'Immediate medical attention, possible surgical intervention', '#d32f2f', 1, '2025-05-14 23:18:52', '2025-05-14 23:18:52'),
(6, 'Cat Scratch/Bite', 'Scratch or bite from cat with potential for infection', 'Medium', 'Cat', 'Puncture wounds, scratches', 'Clean thoroughly, monitor for cat scratch fever symptoms', '#ff9800', 1, '2025-05-14 23:18:52', '2025-05-14 23:18:52'),
(7, 'Rabid Animal Exposure', 'Bite or scratch from suspected rabid animal', 'High', 'Any', 'Any wound from suspected rabid animal', 'Complete post-exposure prophylaxis, rabies immunoglobulin', '#b71c1c', 1, '2025-05-14 23:18:52', '2025-05-14 23:18:52'),
(8, 'Rodent Bite', 'Bite from rat, mouse or other rodent', 'Medium', 'Rodent', 'Small puncture wounds', 'Clean thoroughly, monitor for rat-bite fever', '#ff9800', 1, '2025-05-14 23:18:52', '2025-05-14 23:18:52'),
(9, 'Monkey Bite', 'Bite from monkey with risk of herpes B', 'High', 'Monkey', 'Puncture wounds, lacerations', 'Immediate medical attention, antiviral consideration', '#e53935', 1, '2025-05-14 23:18:52', '2025-05-14 23:18:52'),
(10, 'Livestock Bite', 'Bite from farm animal', 'Medium', 'Livestock', 'Crush injury, laceration', 'Clean thoroughly, tetanus prophylaxis', '#fb8c00', 1, '2025-05-14 23:18:52', '2025-05-14 23:18:52');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `patientId` int(11) NOT NULL,
  `firstName` varchar(50) NOT NULL,
  `lastName` varchar(50) NOT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `dateOfBirth` date DEFAULT NULL,
  `contactNumber` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `barangay` enum('Bubog','Cabatangan','Zone 4-A (Pob.)','Zone 4 (Pob.)','Concepcion','Dos Hermanas','Efigenio Lizares','Zone 7 (Pob.)','Zone 14-B (Pob.)','Zone 12-A (Pob.)','Zone 10 (Pob.)','Zone 5 (Pob.)','Zone 16 (Pob.)','Matab-ang','Zone 9 (Pob.)','Zone 6 (Pob.)','Zone 14 (Pob.)','San Fernando','Zone 15 (Pob.)','Zone 14-A (Pob.)','Zone 11 (Pob.)','Zone 8 (Pob.)','Zone 12 (Pob.)','Zone 1 (Pob.)','Zone 2 (Pob.)','Zone 3 (Pob.)','Katilingban') DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `medicalHistory` text DEFAULT NULL,
  `emergencyContact` varchar(100) DEFAULT NULL,
  `emergencyContactNumber` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `middleName` varchar(45) NOT NULL,
  `province` varchar(155) NOT NULL,
  `emergencyPhone` int(11) NOT NULL,
  `allergies` text DEFAULT NULL,
  `previousRabiesVaccine` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`patientId`, `firstName`, `lastName`, `gender`, `dateOfBirth`, `contactNumber`, `email`, `address`, `barangay`, `city`, `medicalHistory`, `emergencyContact`, `emergencyContactNumber`, `created_at`, `updated_at`, `middleName`, `province`, `emergencyPhone`, `allergies`, `previousRabiesVaccine`) VALUES
(1, 'Meg Ryan', 'Rojo', 'Male', '2003-09-01', '09284520312', NULL, 'Zone-2 Bangga-Aton Talisay City', 'Zone 2 (Pob.)', NULL, NULL, NULL, NULL, '2025-04-27 03:21:25', '2025-05-20 09:22:30', '', '', 0, NULL, NULL),
(2, 'Kathleen', 'Manansala', 'Female', '2003-02-06', '09702954006', 'kathleen@gmail.com', 'Manpower', 'Zone 12 (Pob.)', 'Talisay City', '', 'Kim Dales', '123456789', '2025-05-14 11:57:11', '2025-05-20 09:23:34', 'Dales', 'Negros Occidental', 0, 'Seafoods', 'No'),
(3, 'Juan', 'Dela Cruz', 'Male', '1990-05-15', '09171234567', NULL, '123 Mabini St., Talisay City, Negros Occidental', 'Dos Hermanas', NULL, NULL, NULL, NULL, '2025-05-20 00:52:21', '2025-05-20 09:23:54', '', '', 0, NULL, NULL),
(4, 'Maria ', 'Lopez', 'Female', '1962-08-11', '09171234567', NULL, 'Zone 15 Talisay City Negros Occidental', 'Zone 15 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 09:20:21', '2025-05-20 09:20:21', '', '', 0, NULL, NULL),
(5, 'Juan', 'Cruz', 'Male', '1993-06-25', '09081239841', NULL, 'Prk. Santan, zone 4 \r\n', 'Zone 4 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 09:31:21', '2025-05-20 09:31:21', '', '', 0, NULL, NULL),
(6, 'Elena ', 'Garcia', 'Female', '2006-10-06', '09991230056', NULL, 'Sitio Mambucog, zone 16\r\n', 'Zone 16 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 09:34:07', '2025-05-20 09:34:07', '', '', 0, NULL, NULL),
(7, 'Carmen', 'Rivera', 'Female', '1964-03-18', '09051233498', NULL, 'Prk. Rosas, zone 6\r\n', 'Zone 6 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 09:39:00', '2025-05-20 09:39:00', '', '', 0, NULL, NULL),
(8, 'Andres', 'Torres', 'Male', '1962-05-15', '09181236745', NULL, 'Zone 16', 'Zone 16 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 10:01:03', '2025-05-20 10:01:03', '', '', 0, NULL, NULL),
(9, 'Teresa ', 'Santos', 'Female', '1990-09-08', '09181234992', NULL, 'Prk. Manpower zone 12\r\n', 'Zone 12 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 10:30:34', '2025-05-20 10:30:34', '', '', 0, NULL, NULL),
(10, 'Eric', 'Cooper', 'Male', '2012-08-12', '09612307893', '', 'Sitio Catamnan II, Concepcion', 'Concepcion', 'Talisay City', '', '', NULL, '2025-05-20 11:14:56', '2025-05-20 11:56:51', '', 'Negros Occidental', 0, NULL, 'Unknown'),
(11, 'Maria', 'Cruz', 'Female', '1981-03-20', '09181235411', NULL, 'Prk. Orosa zone 12', 'Zone 12 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:17:29', '2025-05-20 11:17:29', '', '', 0, NULL, NULL),
(12, 'Melissa', ' Griffin', 'Female', '2002-01-13', '09179421695', NULL, 'Prk. Orosa, Zone 12\r\n', 'Zone 12 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:19:15', '2025-05-20 11:19:15', '', '', 0, NULL, NULL),
(13, 'Mercedes ', 'Brown', 'Female', '2001-08-03', '09566192640', NULL, 'Hda. Sta. Teresita, Dos Hermanas\r\n', 'Dos Hermanas', NULL, NULL, NULL, NULL, '2025-05-20 11:21:28', '2025-05-20 11:21:28', '', '', 0, NULL, NULL),
(14, 'Jose', 'Torres', 'Male', '2005-07-07', '09151233602', NULL, 'Hda. Virgen 1, Efigenio Lizares', 'Efigenio Lizares', NULL, NULL, NULL, NULL, '2025-05-20 11:22:26', '2025-05-20 11:22:26', '', '', 0, NULL, NULL),
(15, 'Charles', 'Brave', 'Male', '2002-05-20', '09351671346', NULL, 'Hda. Minuluan, Matab-ang', 'Matab-ang', NULL, NULL, NULL, NULL, '2025-05-20 11:23:47', '2025-05-20 11:23:47', '', '', 0, NULL, NULL),
(16, 'Isabel', 'Garcia', 'Female', '2015-03-25', '09981230247', NULL, 'Prk. 3 zone 14-A', 'Zone 14-A (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:25:10', '2025-05-20 11:25:10', '', '', 0, NULL, NULL),
(17, 'David', 'Stafford', 'Male', '2002-05-20', '09204231037', NULL, 'Pasil, Zone 5', 'Zone 5 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:25:58', '2025-05-20 11:25:58', '', '', 0, NULL, NULL),
(18, 'Ramon', 'Rivera', 'Male', '1939-09-24', '09171234880', NULL, 'Prk. Santan zone 7', 'Zone 7 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:27:25', '2025-05-20 11:27:25', '', '', 0, NULL, NULL),
(19, 'Juan', 'Cruz', 'Male', '1956-07-07', '09566085310', NULL, 'Sitio Masanglad, Cabatangan\r\n', 'Cabatangan', NULL, NULL, NULL, NULL, '2025-05-20 11:28:14', '2025-05-20 11:28:14', '', '', 0, NULL, NULL),
(20, 'Michelle', 'Spencer', 'Female', '1963-11-12', '09453054324', NULL, 'Sitio Patayunan, Concepcion\r\n', 'Concepcion', NULL, NULL, NULL, NULL, '2025-05-20 11:30:09', '2025-05-20 11:30:09', '', '', 0, NULL, NULL),
(21, 'Gloria ', 'Reyes', 'Female', '2022-07-02', '09091234163', NULL, 'Hda. Sta.Catalina, Dos Hermanas', 'Dos Hermanas', NULL, NULL, NULL, NULL, '2025-05-20 11:30:57', '2025-05-20 11:30:57', '', '', 0, NULL, NULL),
(22, 'George', 'Hardin', 'Female', '2006-01-12', '09183076665', NULL, 'Had. Cabanbanan Kilayko. Zone 12A\r\n', 'Zone 12 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:32:38', '2025-05-20 11:32:38', '', '', 0, NULL, NULL),
(23, 'Daniel', 'Mendoza', 'Male', '1972-07-01', '09051233795', NULL, 'Prk. Alusiman zone 12', 'Zone 12 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:33:46', '2025-05-20 11:33:46', '', '', 0, NULL, NULL),
(24, 'Trevor ', 'Gomez', 'Male', '1997-07-25', '09459456983', NULL, 'Prk. Manpower, Zone 12', 'Zone 12 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:34:41', '2025-05-20 11:34:41', '', '', 0, NULL, NULL),
(25, 'Elena', 'Gomez', 'Female', '1994-06-05', '09991235701', NULL, 'Hda. Binaliwan, Efigenio lizares', 'Efigenio Lizares', NULL, NULL, NULL, NULL, '2025-05-20 11:36:15', '2025-05-20 11:36:15', '', '', 0, NULL, NULL),
(26, 'William', 'Mitchell', 'Male', '1994-04-30', '09561888212', NULL, 'Menlo Heights Zone 10', 'Zone 12 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:36:53', '2025-05-20 11:36:53', '', '', 0, NULL, NULL),
(27, 'Manuel', 'Santos', 'Male', '1947-11-18', '09161234054', NULL, 'Lot 1 blk.12 executive village, zone 12-A', 'Zone 12-A (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:38:17', '2025-05-20 11:38:17', '', '', 0, NULL, NULL),
(28, 'Jesus ', 'Santos', 'Male', '1979-04-30', '09613800852', NULL, 'Prk. Riverside Zone 8', 'Zone 8 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:39:33', '2025-05-20 11:39:33', '', '', 0, NULL, NULL),
(29, 'Ligaya ', 'Cruz', 'Female', '2005-12-28', '09171230038', NULL, 'Sitio bat-os, zone 11', 'Zone 11 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:40:49', '2025-05-20 11:40:49', '', '', 0, NULL, NULL),
(30, 'Philip ', 'Andrews', 'Male', '1972-11-23', '09175131096', NULL, 'Had. Birhen III, Efigenio Lizares', 'Efigenio Lizares', NULL, NULL, NULL, NULL, '2025-05-20 11:42:08', '2025-05-20 11:42:08', '', '', 0, NULL, NULL),
(31, 'Andres', 'Mendoza', 'Male', '2014-06-14', '09081234926', NULL, 'Sitio Cabatangan', 'Cabatangan', NULL, NULL, NULL, NULL, '2025-05-20 11:44:15', '2025-05-20 11:44:15', '', '', 0, NULL, NULL),
(32, 'Jorge ', 'Hays', 'Male', '1983-11-22', '09056767138', NULL, 'Fallonia Street, Zone 5', 'Zone 5 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:44:56', '2025-05-20 11:44:56', '', '', 0, NULL, NULL),
(33, 'Sarah ', 'Ho', 'Female', '2017-11-14', '09054111488', NULL, 'Mabini Street, Zone 1', 'Zone 1 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:47:16', '2025-05-20 11:47:16', '', '', 0, NULL, NULL),
(34, 'Carmen', 'Lopez', 'Female', '1994-07-24', '09151235570', NULL, 'Sitio Catamnan, Concepcion', 'Concepcion', NULL, NULL, NULL, NULL, '2025-05-20 11:47:27', '2025-05-20 11:47:27', '', '', 0, NULL, NULL),
(35, 'Renato', 'Garcia', 'Male', '2016-12-05', '09071234282', NULL, 'Blk. 16 Lot 8 Country Homes subd. Dos Hermanas', 'Dos Hermanas', NULL, NULL, NULL, NULL, '2025-05-20 11:49:15', '2025-05-20 11:49:15', '', '', 0, NULL, NULL),
(36, 'Kevin ', 'Warren', 'Male', '1997-04-27', '09356581334', NULL, 'Gahili-usa, Zone 4', 'Zone 4-A (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:50:07', '2025-05-20 11:50:07', '', '', 0, NULL, NULL),
(37, 'Teresa', 'Rivera', 'Female', '1987-04-14', '09971235846', NULL, 'Hda. Cabanbanan Sian, zone 12-A', 'Zone 12-A (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:51:23', '2025-05-20 11:51:23', '', '', 0, NULL, NULL),
(38, 'Jason ', 'Thompson', 'Male', '1992-02-17', '09174184430', NULL, 'Prk. Kapayas, Dos Hermanas', 'Dos Hermanas', NULL, NULL, NULL, NULL, '2025-05-20 11:52:17', '2025-05-20 11:52:17', '', '', 0, NULL, NULL),
(39, 'Juan', 'Gomez', 'Male', '1983-03-04', '09191234675', NULL, 'Hda. Mana-ul, matab-ang', 'Matab-ang', NULL, NULL, NULL, NULL, '2025-05-20 11:53:56', '2025-05-20 11:53:56', '', '', 0, NULL, NULL),
(40, 'Tara ', 'Martinez', 'Female', '1993-07-09', '09561782910', NULL, 'Prk. Greenhills, Zone 3', 'Zone 3 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:55:38', '2025-05-20 11:55:38', '', '', 0, NULL, NULL),
(41, 'Rosa ', 'Cruz', 'Female', '1965-05-25', '09061233890', NULL, 'Sitio Catamnan, Concepcion', 'Concepcion', NULL, NULL, NULL, NULL, '2025-05-20 11:56:54', '2025-05-20 11:56:54', '', '', 0, NULL, NULL),
(42, 'Jason ', 'Soto', 'Male', '1990-07-10', '09058494635', NULL, 'Prk Santa, Zone 4\r\n', 'Zone 4 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:57:51', '2025-05-20 11:57:51', '', '', 0, NULL, NULL),
(43, 'Carlos', 'Santos', 'Male', '1982-10-25', '09181235017', NULL, 'Lot.17 Blk.7  zone 12-A', 'Zone 12-A (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 11:59:01', '2025-05-20 11:59:01', '', '', 0, NULL, NULL),
(44, 'Tyler ', 'Robinson', 'Male', '1958-04-15', '09063413866', NULL, 'Sitio Magcurao, Katalingban\r\n', 'Katilingban', NULL, NULL, NULL, NULL, '2025-05-20 11:59:57', '2025-05-20 11:59:57', '', '', 0, NULL, NULL),
(45, 'Maria', 'Lopez', 'Female', '2011-08-11', '09161233407', NULL, 'prk. Okra, dos hermanas', 'Dos Hermanas', NULL, NULL, NULL, NULL, '2025-05-20 12:01:10', '2025-05-20 12:01:10', '', '', 0, NULL, NULL),
(46, 'Mary ', 'Taylor', 'Female', '1964-11-08', '09056712328', NULL, 'Zone 16\r\n', 'Zone 12-A (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 12:03:09', '2025-05-20 12:03:09', '', '', 0, NULL, NULL),
(47, 'Jose ', 'Garcia', 'Male', '1997-01-06', '09981235433', NULL, 'brgy. bulanon, sagay city/zone 6', 'Zone 6 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 12:28:21', '2025-05-20 12:28:21', '', '', 0, NULL, NULL),
(48, 'Elena', 'Rivera', 'Female', '2015-01-15', '09171233761', NULL, 'Hda. Mana-ul, Matab-ang', 'Matab-ang', NULL, NULL, NULL, NULL, '2025-05-20 12:30:38', '2025-05-20 12:30:38', '', '', 0, NULL, NULL),
(49, 'Ernest ', 'Dougherty', 'Male', '2012-02-20', '09359839550', NULL, 'Zone 15, Prk. Tumpoil\r\n', 'Zone 12-A (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 12:31:52', '2025-05-20 12:31:52', '', '', 0, NULL, NULL),
(50, 'Emilio ', 'Mendoza', 'Male', '2023-12-17', '09051234912', NULL, 'Hda. Sta. Teresita Extension, Dos hermanas', 'Dos Hermanas', NULL, NULL, NULL, NULL, '2025-05-20 12:33:12', '2025-05-20 12:33:12', '', '', 0, NULL, NULL),
(51, 'Hayden ', 'Phillips', 'Male', '1994-02-10', '09065311629', NULL, 'Zone 5, Prk. Pasil\r\n', 'Zone 5 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 12:33:24', '2025-05-20 12:33:24', '', '', 0, NULL, NULL),
(52, 'Juana', 'Torres', 'Female', '1988-01-15', '09091235648', NULL, 'Prk. punao, zone 5', 'Zone 5 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 12:34:42', '2025-05-20 12:34:42', '', '', 0, NULL, NULL),
(53, 'Alyssa', 'Rosales', 'Female', '2018-02-27', '09174270566', NULL, 'Zone 9, Prk.Matigayun\r\n', 'Zone 9 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 12:35:03', '2025-05-20 12:35:03', '', '', 0, NULL, NULL),
(54, 'Ramon', 'Garcia', 'Male', '2019-01-24', '09961234572', NULL, 'Bangga aton, zone 2', 'Zone 2 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 12:36:15', '2025-05-20 12:36:15', '', '', 0, NULL, NULL),
(55, 'Jennifer ', 'Jimenez', 'Female', '2017-10-11', '09211615479', NULL, 'Matab-ang, Concepcion\r\n', 'Zone 1 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 12:37:00', '2025-05-20 12:37:00', '', '', 0, NULL, NULL),
(56, 'Isabel', 'Lopez', 'Female', '2008-06-29', '09151233286', NULL, 'Jayme St. zone 5', 'Zone 5 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 12:38:37', '2025-05-20 12:38:37', '', '', 0, NULL, NULL),
(57, 'Trevor ', 'Walker', 'Male', '1969-07-27', '09566591944', NULL, 'Matab-ang, Concepcion\r\n', 'Matab-ang', NULL, NULL, NULL, NULL, '2025-05-20 12:38:58', '2025-05-20 12:38:58', '', '', 0, NULL, NULL),
(58, 'Antonio', 'Reyes', 'Male', '2021-02-12', '09191235794', NULL, 'Prk. Sagay, zone 3', 'Zone 3 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 12:40:24', '2025-05-20 12:40:24', '', '', 0, NULL, NULL),
(59, 'Thomas ', 'Jones', 'Male', '2001-11-01', '09355989487', NULL, 'Zone 10', 'Concepcion', NULL, NULL, NULL, NULL, '2025-05-20 12:40:50', '2025-05-20 12:40:50', '', '', 0, NULL, NULL),
(60, 'Gloria ', 'Mendoza', 'Female', '2014-04-11', '09071233359', NULL, 'Prk. Mahinangpanon, zone 4-A', 'Zone 4-A (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 12:42:55', '2025-05-20 12:42:55', '', '', 0, NULL, NULL),
(61, 'Janet ', 'Rodriguez', 'Female', '1966-04-13', '09060477058', NULL, 'Zone 12A', 'Zone 12 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 12:43:14', '2025-05-20 12:43:14', '', '', 0, NULL, NULL),
(62, 'Daniel ', 'Lopez', 'Male', '1997-03-26', '09081235147', NULL, 'Hda. Dos hermanas, Katilingban', 'Katilingban', NULL, NULL, NULL, NULL, '2025-05-20 12:44:40', '2025-05-20 12:44:40', '', '', 0, NULL, NULL),
(63, 'Charles ', 'Thompson', 'Male', '1985-08-07', '09563775136', NULL, 'blk 35A lot 20 CVH, zone 12- A\r\n', 'Zone 12-A (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 12:45:16', '2025-05-20 12:45:16', '', '', 0, NULL, NULL),
(64, 'Rosa', 'Santos', 'Female', '2007-05-23', '09991233205', NULL, 'Prk. lagang, zone 3', 'Zone 3 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 12:46:17', '2025-05-20 12:46:17', '', '', 0, NULL, NULL),
(65, 'Manuel ', 'Rivera', 'Male', '2022-07-10', '09171235130', NULL, 'Hda. Alusiman, Concepcion', 'Concepcion', NULL, NULL, NULL, NULL, '2025-05-20 12:48:06', '2025-05-20 12:48:06', '', '', 0, NULL, NULL),
(66, 'Teresa', 'Cruz', 'Female', '2012-11-26', '09161233702', NULL, 'Adelfa St. Menlo Phase 3, zone 10', 'Zone 10 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 12:51:49', '2025-05-20 12:51:49', '', '', 0, NULL, NULL),
(67, 'Carlos', 'Mendoza', 'Male', '1973-11-05', '09061230074', NULL, 'Hda. Virgel del pilar, Efigenio Lizares', 'Efigenio Lizares', NULL, NULL, NULL, NULL, '2025-05-20 12:53:34', '2025-05-20 12:53:34', '', '', 0, NULL, NULL),
(68, 'Christine', 'Octavio', 'Female', '1965-12-17', '09564269712', NULL, 'lot 10 blk 7 zone 12- A', 'Zone 12-A (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 12:55:23', '2025-05-20 12:55:23', '', '', 0, NULL, NULL),
(69, 'Elena ', 'Torres', 'Female', '2016-03-14', '09151233988', NULL, 'Prk. Lutik, zone 15', 'Zone 15 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 12:55:31', '2025-05-20 12:55:31', '', '', 0, NULL, NULL),
(70, 'Andres', 'Garcia', 'Male', '1988-11-20', '09091235260', NULL, 'Hope village, San fernando', 'San Fernando', NULL, NULL, NULL, NULL, '2025-05-20 12:57:09', '2025-05-20 12:57:09', '', '', 0, NULL, NULL),
(71, 'John Vincent', 'Deoz', 'Male', '2015-07-15', '09209020185', NULL, 'lot 3 blk 1 zone 12- A\r\n', 'Cabatangan', NULL, NULL, NULL, NULL, '2025-05-20 12:58:09', '2025-05-20 12:58:09', '', '', 0, NULL, NULL),
(72, 'Ligaya', 'Lopez', 'Female', '1963-12-09', '09971235003', NULL, 'Prk. Maabi-abihon, matab-ang', 'Matab-ang', NULL, NULL, NULL, NULL, '2025-05-20 12:59:53', '2025-05-20 12:59:53', '', '', 0, NULL, NULL),
(73, 'Stephen ', 'Garcia', 'Male', '1991-12-23', '09053761261', NULL, 'menlo phase 3 , zone 10', 'Zone 10 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 13:01:33', '2025-05-20 13:01:33', '', '', 0, NULL, NULL),
(74, 'Jose', 'Reyes', 'Male', '1967-08-19', '09181235622', NULL, 'Prk. Everlasting, zone 11', 'Zone 11 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 13:03:21', '2025-05-20 13:03:21', '', '', 0, NULL, NULL),
(75, 'Carmen ', 'Mendoza', 'Female', '2012-02-22', '09161234540', NULL, 'Hda. Esmeralda II, San Fernando', 'San Fernando', NULL, NULL, NULL, NULL, '2025-05-20 13:05:34', '2025-05-20 13:05:34', '', '', 0, NULL, NULL),
(76, 'Emilio ', 'Lopez', 'Male', '1993-06-13', '09071235369', NULL, 'blk. 35A Lot 20 CVH, zone 12-A', 'Zone 12-A (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 13:07:20', '2025-05-20 13:07:20', '', '', 0, NULL, NULL),
(77, 'Juana', 'Garcia', 'Female', '1966-12-27', '09961234851', NULL, 'Prk. Malipayon, zone 4-A', 'Zone 4-A (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 13:10:02', '2025-05-20 13:10:02', '', '', 0, NULL, NULL),
(78, 'Ramon', 'Mendoza', 'Male', '2017-05-03', '09171233097', NULL, 'Hda. Mana-ul, Matab-ang', 'Matab-ang', NULL, NULL, NULL, NULL, '2025-05-20 13:13:08', '2025-05-20 13:13:08', '', '', 0, NULL, NULL),
(79, 'Maria', 'Reyes', 'Female', '2002-09-01', '09081233348', NULL, 'blk.19 lot 6 zone 15', 'Zone 15 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 13:18:23', '2025-05-20 13:18:23', '', '', 0, NULL, NULL),
(80, 'Antonio ', 'Cruz', 'Male', '1960-06-24', '09151235176', NULL, 'Prk. Kapayas, Dos hermanas', 'Dos Hermanas', NULL, NULL, NULL, NULL, '2025-05-20 13:21:14', '2025-05-20 13:21:14', '', '', 0, NULL, NULL),
(81, 'Isabel', 'Mendoza', 'Female', '2006-06-02', '09191233264', NULL, 'Hda. Nilo lizares, matab-ang', 'Matab-ang', NULL, NULL, NULL, NULL, '2025-05-20 13:23:31', '2025-05-20 13:23:31', '', '', 0, NULL, NULL),
(82, 'Juan', 'Santos', 'Male', '2022-03-19', '09061234733', NULL, 'Prk.6 zone 14-A', 'Zone 14-A (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 13:25:10', '2025-05-20 13:25:10', '', '', 0, NULL, NULL),
(83, 'Gloria ', 'Rivera', 'Female', '2005-10-15', '09181235218', NULL, 'Hda. Sta. Teresita, Dos hermanas', 'Dos Hermanas', NULL, NULL, NULL, NULL, '2025-05-20 13:27:10', '2025-05-20 13:27:10', '', '', 0, NULL, NULL),
(84, 'Daniel ', 'Cruz', 'Male', '2008-07-01', '09091233155', NULL, 'Hda. Cabanbanan Sian, zone 12-A', 'Zone 12-A (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 13:29:31', '2025-05-20 13:29:31', '', '', 0, NULL, NULL),
(85, 'Rosa', 'Garcia', 'Female', '2012-12-21', '09981234984', NULL, 'Prk. Santan zone 7', 'Zone 7 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 13:31:02', '2025-05-20 13:31:02', '', '', 0, NULL, NULL),
(86, 'Renato ', 'Lopez', 'Male', '1962-02-22', '09171234029', NULL, 'Prk. Mabinuligon, zone 4-A', 'Zone 4-A (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 13:33:19', '2025-05-20 13:33:19', '', '', 0, NULL, NULL),
(87, 'Tanya', 'Angeles', 'Female', '1986-12-23', '09217494020', NULL, 'Hda. Mana-ul, Matab-ang\r\n', 'Zone 1 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 13:35:10', '2025-05-20 13:35:10', '', '', 0, NULL, NULL),
(88, 'Teresa', 'Mendoza', 'Female', '1987-10-01', '09051235613', NULL, 'Prk. Pasil zone 5', 'Zone 5 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 13:35:11', '2025-05-20 13:35:11', '', '', 0, NULL, NULL),
(89, 'Carlos', 'Garcia', 'Male', '2023-11-11', '09161233322', NULL, 'Hda. Balong, matab-ang', 'Matab-ang', NULL, NULL, NULL, NULL, '2025-05-20 13:36:55', '2025-05-20 13:36:55', '', '', 0, NULL, NULL),
(90, 'Roger', 'Lizares', 'Male', '2007-08-27', '09355049417', NULL, 'prk. Malipayon zone 4-A', 'Zone 10 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 13:37:02', '2025-05-20 13:37:02', '', '', 0, NULL, NULL),
(91, 'James', 'Garcia', 'Male', '2008-11-21', '09612403612', NULL, 'Hda. Cabanbanan Sian, zone 12-A', 'Zone 12-A (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 13:39:04', '2025-05-20 13:39:04', '', '', 0, NULL, NULL),
(92, 'Elena', 'Cruz', 'Female', '2021-11-11', '09961235108', NULL, 'Hda. mana-ul, matab-ang', 'Matab-ang', NULL, NULL, NULL, NULL, '2025-05-20 13:39:17', '2025-05-20 13:39:17', '', '', 0, NULL, NULL),
(93, 'Emilio', 'Reyes', 'Male', '2003-07-11', '09181234804', NULL, 'Domingo lizares St. zone 1', 'Zone 1 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 13:40:42', '2025-05-20 13:40:42', '', '', 0, NULL, NULL),
(94, 'Joseph', 'Duremdez', 'Male', '1984-07-28', '09216566191', NULL, 'bangga aton, zone 2\r\n', 'Zone 2 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 13:41:51', '2025-05-20 13:41:51', '', '', 0, NULL, NULL),
(95, 'Andrei', 'Cian', 'Male', '1957-03-25', '09216929875', NULL, 'Sitio Mambucog, zone 16\r\n', 'Zone 16 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 13:43:41', '2025-05-20 13:43:41', '', '', 0, NULL, NULL),
(96, 'Chrstine', 'Ramirez', 'Female', '1973-02-25', '09173101094', NULL, 'Hda. San Juan Yusay, Dos Hermanas\r\n', 'Dos Hermanas', NULL, NULL, NULL, NULL, '2025-05-20 13:46:20', '2025-05-20 13:46:20', '', '', 0, NULL, NULL),
(97, 'Anthony', 'Dela Cruz', 'Male', '2002-10-19', '09067192261', NULL, 'Blk. 16 lot 8 Country Homes Subd. Dos Hermanas\r\n', 'Dos Hermanas', NULL, NULL, NULL, NULL, '2025-05-20 13:49:33', '2025-05-20 13:49:33', '', '', 0, NULL, NULL),
(98, 'Miguel', 'Santino', 'Male', '1960-07-08', '09211745793', NULL, 'Blk.8 Lot 21 St.Paul\'s village, zone 16\r\n', 'Zone 16 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-20 13:52:27', '2025-05-20 13:52:27', '', '', 0, NULL, NULL),
(99, 'Andres', 'Ramos', 'Male', '1994-06-15', '09566631782', '', 'Sitio Mambucog, zone 16', 'Zone 16 (Pob.)', 'Talisay City', '', '', NULL, '2025-05-20 13:55:11', '2025-05-20 17:35:06', '', 'Negros Occidental', 0, NULL, 'Unknown'),
(100, 'Anthony', 'Angeles', 'Other', '2003-02-27', '09171234567', NULL, 'zone55', 'Zone 5 (Pob.)', NULL, NULL, NULL, NULL, '2025-05-21 01:53:03', '2025-05-21 01:53:03', '', '', 0, NULL, NULL),
(101, 'meg', 'rojo', 'Other', '2005-12-12', '12345678910', NULL, 'katilingban talisay city', 'Katilingban', NULL, NULL, NULL, NULL, '2025-11-11 06:47:28', '2025-11-11 06:47:28', '', '', 0, NULL, NULL),
(102, 'christel ann', 'cahilig', 'Female', '2005-12-09', '09165067993', '', 'Hda. Bantud', 'Concepcion', 'Talisay City', '', '', '09165067993', '2025-11-11 07:42:21', '2025-11-11 07:42:21', '', 'Negros Occidental', 0, '', 'No'),
(103, 'Anthony ', 'Panolino', 'Male', '2025-11-19', '09284520312', NULL, 'Bangga-Aton Talisay City', 'Zone 9 (Pob.)', NULL, NULL, NULL, NULL, '2025-11-19 05:42:32', '2025-11-19 05:42:32', '', '', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `reportId` int(11) NOT NULL,
  `patientId` int(11) DEFAULT NULL,
  `staffId` int(11) DEFAULT NULL,
  `biteDate` date NOT NULL,
  `reportDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `animalType` enum('Dog','Cat','Rat','Monkey','Bat','Other') NOT NULL,
  `animalOtherType` varchar(50) DEFAULT NULL,
  `animalOwnership` enum('Stray','Owned by patient','Owned by neighbor','Owned by unknown person','Unknown') NOT NULL,
  `ownerName` varchar(100) DEFAULT NULL,
  `ownerContact` varchar(20) DEFAULT NULL,
  `animalStatus` enum('Alive','Dead','Unknown') NOT NULL DEFAULT 'Unknown',
  `animalVaccinated` enum('Yes','No','Unknown') NOT NULL DEFAULT 'Unknown',
  `biteLocation` varchar(255) NOT NULL,
  `biteType` enum('Category I','Category II','Category III') NOT NULL,
  `multipleBites` tinyint(1) NOT NULL DEFAULT 0,
  `provoked` enum('Yes','No','Unknown') NOT NULL DEFAULT 'Unknown',
  `washWithSoap` tinyint(1) NOT NULL DEFAULT 0,
  `rabiesVaccine` tinyint(1) NOT NULL DEFAULT 0,
  `rabiesVaccineDate` date DEFAULT NULL,
  `antiTetanus` tinyint(1) NOT NULL DEFAULT 0,
  `antiTetanusDate` date DEFAULT NULL,
  `antibiotics` tinyint(1) NOT NULL DEFAULT 0,
  `antibioticsDetails` varchar(255) DEFAULT NULL,
  `referredToHospital` tinyint(1) NOT NULL DEFAULT 0,
  `hospitalName` varchar(255) DEFAULT NULL,
  `followUpDate` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','in_progress','completed','referred') NOT NULL DEFAULT 'pending',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`reportId`, `patientId`, `staffId`, `biteDate`, `reportDate`, `animalType`, `animalOtherType`, `animalOwnership`, `ownerName`, `ownerContact`, `animalStatus`, `animalVaccinated`, `biteLocation`, `biteType`, `multipleBites`, `provoked`, `washWithSoap`, `rabiesVaccine`, `rabiesVaccineDate`, `antiTetanus`, `antiTetanusDate`, `antibiotics`, `antibioticsDetails`, `referredToHospital`, `hospitalName`, `followUpDate`, `notes`, `status`, `updated_at`) VALUES
(1, 1, 1, '2025-04-16', '2025-04-27 03:21:25', 'Dog', NULL, 'Stray', NULL, NULL, 'Alive', 'Unknown', 'Left leg', 'Category III', 0, 'Unknown', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, '2025-04-28', 'Follow-up for tomorrow with the doctor on-site', 'pending', '2025-04-27 03:21:25'),
(2, 1, 1, '2025-05-07', '2025-05-07 01:39:45', 'Dog', NULL, 'Owned by neighbor', NULL, '09284520312', 'Alive', 'Unknown', 'Left leg', 'Category III', 1, 'Unknown', 0, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'pending', '2025-05-07 01:39:45'),
(3, 3, 1, '2025-05-20', '2025-05-20 00:52:21', 'Dog', NULL, 'Stray', NULL, NULL, 'Alive', 'Unknown', 'Right Leg', 'Category III', 0, 'Unknown', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, '2025-05-22', NULL, 'pending', '2025-05-20 01:34:36'),
(4, 2, 1, '2025-05-20', '2025-05-20 05:14:56', 'Cat', NULL, 'Stray', NULL, NULL, 'Alive', 'Unknown', 'Left Foot', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, '2025-05-21', NULL, 'pending', '2025-05-20 05:14:56'),
(5, 4, 1, '2025-05-20', '2025-05-20 09:20:21', 'Cat', NULL, 'Owned by patient', 'Maria Lopez', '09171234567', 'Alive', 'Yes', 'Right Foot', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-18', 0, NULL, 0, NULL, '2025-03-20', NULL, 'completed', '2025-05-20 09:20:21'),
(6, 5, 1, '2025-05-20', '2025-05-20 09:31:21', 'Dog', NULL, 'Owned by patient', 'Juan Cruz', '09081239841', 'Alive', 'Yes', 'Right Inner Thigh', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-18', 0, NULL, 0, NULL, '2025-03-20', NULL, 'completed', '2025-05-20 09:31:21'),
(7, 6, 1, '2025-03-20', '2025-05-20 09:34:07', 'Cat', NULL, 'Owned by patient', 'Elena Garcia', '09991230056', 'Alive', 'Yes', 'Right Hand', 'Category III', 0, 'No', 1, 0, NULL, 1, '2025-03-20', 0, NULL, 0, NULL, '2025-03-22', NULL, 'completed', '2025-05-20 09:34:07'),
(8, 7, 1, '2025-05-20', '2025-05-20 09:39:00', 'Cat', NULL, 'Owned by patient', 'Carmen Rivera', '09051233498', 'Alive', 'No', 'Left Foot', 'Category II', 0, 'No', 1, 1, '2025-03-24', 1, '2025-03-21', 0, NULL, 0, NULL, '2025-03-21', NULL, 'completed', '2025-05-20 09:39:00'),
(9, 8, 1, '2025-03-20', '2025-05-20 10:01:03', 'Dog', NULL, 'Owned by patient', 'Andres Torres', '09181236745', 'Alive', 'No', 'Face', 'Category III', 0, 'No', 1, 1, '2025-03-21', 1, '2025-03-20', 0, NULL, 0, NULL, '2025-03-21', NULL, 'completed', '2025-05-20 10:01:03'),
(10, 9, 1, '2025-03-03', '2025-05-20 10:30:34', 'Dog', NULL, 'Stray', NULL, NULL, 'Alive', 'Unknown', 'Right Popliteal Area', 'Category II', 0, 'Unknown', 1, 1, '2025-03-05', 1, '2025-03-03', 0, NULL, 0, NULL, '2025-03-05', NULL, 'completed', '2025-05-20 10:30:34'),
(11, 10, 1, '2025-02-04', '2025-05-20 11:14:56', 'Dog', NULL, 'Owned by patient', NULL, '09612307893', 'Alive', 'No', 'Right Leg', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'pending', '2025-05-20 11:14:56'),
(12, 11, 1, '2025-03-25', '2025-05-20 11:17:29', 'Dog', NULL, 'Owned by patient', 'Maria Cruz', '09181235411', 'Alive', 'No', 'Left Leg', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-25', 0, NULL, 0, NULL, '2025-03-25', NULL, 'completed', '2025-05-20 11:17:29'),
(13, 12, 1, '2025-02-05', '2025-05-20 11:19:15', 'Dog', NULL, 'Owned by patient', 'Melissa Griffin', '09179421695', 'Alive', 'Yes', 'Right Hand', 'Category II', 0, 'Unknown', 1, 0, NULL, 1, '2025-02-06', 0, NULL, 0, NULL, NULL, NULL, 'pending', '2025-05-20 11:19:15'),
(14, 13, 1, '2025-02-04', '2025-05-20 11:21:28', 'Dog', NULL, 'Owned by patient', 'Mercedes Brown', '09566192640', 'Alive', 'No', 'L Leg', 'Category II', 0, 'Unknown', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'pending', '2025-05-20 11:21:28'),
(15, 14, 1, '2025-03-24', '2025-05-20 11:22:26', 'Cat', NULL, 'Stray', NULL, NULL, 'Alive', 'Yes', 'Right Forearm', 'Category III', 0, 'No', 1, 1, '2025-03-24', 1, '2025-03-25', 0, NULL, 0, NULL, '2025-03-25', NULL, 'completed', '2025-05-20 11:22:26'),
(16, 15, 1, '2025-02-04', '2025-05-20 11:23:47', 'Cat', NULL, 'Owned by neighbor', 'Christina Charles', '09351671348', 'Alive', 'Yes', 'L Leg', 'Category II', 0, 'Unknown', 1, 0, NULL, 1, '2025-02-04', 0, NULL, 0, NULL, NULL, NULL, 'pending', '2025-05-20 11:23:47'),
(17, 16, 1, '2025-03-25', '2025-05-20 11:25:10', 'Cat', NULL, 'Owned by patient', 'Isabel Garcia', '09981230247', 'Alive', 'No', 'Both legs', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-24', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 11:25:10'),
(18, 17, 1, '2025-02-04', '2025-05-20 11:25:58', 'Cat', NULL, 'Owned by patient', 'David Stafford', '09204231037', 'Alive', 'No', 'R Hand', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'pending', '2025-05-20 11:25:58'),
(19, 18, 1, '2025-03-24', '2025-05-20 11:27:25', 'Dog', NULL, 'Owned by patient', 'Ramon Rivera', '09171234880', 'Alive', 'Unknown', 'Left Wrist', 'Category II', 0, 'Unknown', 1, 0, NULL, 1, '2025-03-24', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 11:27:25'),
(20, 19, 1, '2025-02-04', '2025-05-20 11:28:14', 'Dog', NULL, 'Owned by patient', 'Juan Cruz', '09566085310', 'Alive', 'Yes', 'L Hand', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'pending', '2025-05-20 11:28:14'),
(21, 20, 1, '2025-02-04', '2025-05-20 11:30:09', 'Dog', NULL, 'Owned by neighbor', 'Juan Meowy', '09566085312', 'Alive', 'Unknown', 'R Hand', 'Category II', 0, 'Unknown', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'pending', '2025-05-20 11:30:09'),
(22, 21, 1, '2025-03-24', '2025-05-20 11:30:57', 'Dog', NULL, 'Owned by patient', 'Gloria Reyes', '09091234163', 'Alive', 'Unknown', 'Right Knee', 'Category III', 0, 'Unknown', 1, 1, '2025-03-26', 1, '2025-03-24', 0, NULL, 0, NULL, '2025-03-26', NULL, 'pending', '2025-05-20 11:30:57'),
(23, 22, 1, '2025-02-04', '2025-05-20 11:32:38', 'Cat', NULL, 'Owned by patient', 'George Hardin', '09183076665', 'Alive', 'Yes', 'R Leg', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'pending', '2025-05-20 11:32:38'),
(24, 23, 1, '2025-03-25', '2025-05-20 11:33:46', 'Cat', NULL, 'Owned by patient', 'Daniel Mendoza', '09051233795', 'Alive', 'Unknown', 'Right Neck Area', 'Category II', 0, 'Unknown', 1, 0, NULL, 1, '2025-03-25', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 11:33:46'),
(25, 24, 1, '2025-02-04', '2025-05-20 11:34:41', 'Cat', NULL, 'Stray', NULL, NULL, 'Alive', 'No', 'R Hand', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'pending', '2025-05-20 11:34:41'),
(26, 25, 1, '2025-03-25', '2025-05-20 11:36:15', 'Cat', NULL, 'Owned by patient', 'Elena Gomez', '09991235701', 'Alive', 'Unknown', 'Right Leg', 'Category II', 0, 'Unknown', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 11:36:15'),
(27, 26, 1, '2025-02-05', '2025-05-20 11:36:53', 'Cat', NULL, 'Stray', NULL, NULL, 'Alive', 'Unknown', 'L Hand', 'Category II', 0, 'Unknown', 1, 0, NULL, 1, '2025-02-05', 0, NULL, 0, NULL, NULL, NULL, 'pending', '2025-05-20 11:36:53'),
(28, 27, 1, '2025-03-26', '2025-05-20 11:38:17', 'Dog', NULL, 'Owned by patient', 'Manuel Santos', '09161234054', 'Alive', 'No', 'Right Hand', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'pending', '2025-05-20 11:38:17'),
(29, 28, 1, '2025-02-06', '2025-05-20 11:39:33', 'Cat', NULL, 'Owned by patient', 'Jesus Santos', '09613800852', 'Alive', 'Yes', 'R Leg', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'pending', '2025-05-20 11:39:33'),
(30, 29, 1, '2025-03-25', '2025-05-20 11:40:49', 'Cat', NULL, 'Owned by patient', 'Ligaya Cruz', '09171230038', 'Alive', 'No', 'Back Area', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 11:40:49'),
(31, 30, 1, '2025-02-07', '2025-05-20 11:42:08', 'Cat', NULL, '', 'Philip Andrews', '09175131096', 'Alive', 'No', 'R Hand', 'Category II', 0, 'No', 1, 0, '0000-00-00', 0, '0000-00-00', 0, '', 0, '', '0000-00-00', '', 'pending', '2025-05-20 13:01:18'),
(32, 31, 1, '2025-03-25', '2025-05-20 11:44:15', 'Dog', NULL, 'Owned by patient', 'Andres Mendoza', '09081234926', 'Alive', 'No', 'Right Thigh', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-25', 0, NULL, 0, NULL, '2025-03-26', NULL, 'in_progress', '2025-05-20 11:44:15'),
(33, 32, 1, '2025-02-07', '2025-05-20 11:44:56', 'Cat', NULL, 'Owned by neighbor', 'Christina Charles', '09351671349', 'Alive', 'Yes', 'L Foot', 'Category II', 0, 'Yes', 1, 0, NULL, 1, '2025-02-07', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 11:44:56'),
(34, 33, 1, '2025-02-07', '2025-05-20 11:47:16', 'Cat', NULL, 'Stray', NULL, NULL, 'Alive', 'Unknown', 'R Foot', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-07-02', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 11:47:16'),
(35, 34, 1, '2025-03-25', '2025-05-20 11:47:27', 'Cat', NULL, 'Owned by patient', 'Carmen Lopez', '09151235570', 'Alive', 'No', 'Right Foot', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-25', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 11:47:27'),
(36, 35, 1, '2025-03-22', '2025-05-20 11:49:15', 'Dog', NULL, 'Owned by patient', 'Renato Garcia', '09071234282', 'Alive', 'No', 'Right Toe', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'pending', '2025-05-20 11:49:15'),
(37, 36, 1, '2025-02-08', '2025-05-20 11:50:07', 'Dog', NULL, 'Owned by patient', 'Kevin Warren', '09356581334', 'Alive', 'Yes', 'L Hand', 'Category II', 0, 'No', 1, 1, '2025-02-11', 1, '2025-02-08', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 11:50:07'),
(38, 37, 1, '2025-03-24', '2025-05-20 11:51:23', 'Cat', NULL, 'Owned by patient', 'Teresa Rivera', '09971235846', 'Alive', 'No', 'Right Hand', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-24', 0, NULL, 0, NULL, '2025-03-25', NULL, 'completed', '2025-05-20 11:51:23'),
(39, 38, 1, '2025-02-08', '2025-05-20 11:52:17', 'Dog', NULL, 'Stray', NULL, NULL, 'Alive', 'Unknown', 'L leg', 'Category I', 0, 'No', 1, 1, '2025-02-08', 1, '2025-02-11', 0, NULL, 0, NULL, NULL, NULL, 'pending', '2025-05-20 11:52:17'),
(40, 39, 1, '2025-03-25', '2025-05-20 11:53:56', 'Dog', NULL, 'Owned by patient', 'Juan Gomez', '09191234675', 'Alive', 'No', 'Left Thigh', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-25', 0, NULL, 0, NULL, '2025-03-27', NULL, 'completed', '2025-05-20 11:53:56'),
(41, 40, 1, '2025-02-08', '2025-05-20 11:55:38', 'Rat', NULL, 'Unknown', NULL, NULL, 'Alive', 'No', 'Nose Bridge', 'Category II', 0, 'No', 1, 1, '2025-02-11', 1, '2025-02-08', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 11:55:38'),
(42, 41, 1, '2025-03-26', '2025-05-20 11:56:54', 'Cat', NULL, 'Owned by patient', 'Rosa Cruz', '09061233890', 'Alive', 'No', 'Right Hand', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-26', 0, NULL, 0, NULL, '2025-03-27', NULL, 'completed', '2025-05-20 11:56:54'),
(43, 42, 1, '2025-02-08', '2025-05-20 11:57:51', 'Cat', NULL, 'Stray', NULL, NULL, 'Alive', 'Unknown', 'R Foot', 'Category II', 0, 'No', 1, 1, '2025-02-12', 1, '2025-02-08', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 11:57:51'),
(44, 43, 1, '2025-03-26', '2025-05-20 11:59:01', 'Cat', NULL, 'Owned by patient', 'Carlos Santos', '09181235017', 'Alive', 'No', 'Left Foot', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-26', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 11:59:01'),
(45, 44, 1, '2025-02-09', '2025-05-20 11:59:57', 'Cat', NULL, 'Stray', NULL, NULL, 'Alive', 'Unknown', 'R Foot', 'Category II', 0, 'No', 1, 1, '2025-02-13', 1, '2025-02-09', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 11:59:57'),
(46, 45, 1, '2025-03-25', '2025-05-20 12:01:10', 'Dog', NULL, 'Owned by patient', 'Maria Lopez', '09161233407', 'Alive', 'No', 'Back Area', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-25', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:01:10'),
(47, 46, 1, '2025-02-09', '2025-05-20 12:03:09', 'Dog', NULL, 'Owned by patient', 'Mary Taylor', '09056712328', 'Alive', 'Yes', 'R Leg', 'Category II', 0, 'No', 1, 0, NULL, 1, NULL, 0, NULL, 0, NULL, '2025-02-09', NULL, 'completed', '2025-05-20 12:03:09'),
(48, 47, 1, '2025-03-19', '2025-05-20 12:28:21', 'Dog', NULL, 'Stray', NULL, NULL, 'Alive', 'Unknown', 'Left Leg', 'Category II', 0, 'Unknown', 1, 1, '2025-03-20', 1, '2025-03-19', 0, NULL, 0, NULL, '2025-03-20', NULL, 'completed', '2025-05-20 12:28:21'),
(49, 48, 1, '2025-03-26', '2025-05-20 12:30:38', 'Dog', NULL, 'Owned by patient', 'Elena Rivera', '09171233761', 'Alive', 'No', 'Left Knee', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-26', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:30:38'),
(50, 49, 1, '2025-02-09', '2025-05-20 12:31:52', 'Dog', NULL, 'Owned by patient', 'Ernest Dougherty', '09359839550', 'Alive', 'Yes', 'L Abdominal', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-02-09', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:31:52'),
(51, 50, 1, '2025-03-26', '2025-05-20 12:33:12', 'Cat', NULL, 'Stray', NULL, NULL, 'Alive', 'Unknown', 'Right Lower Leg', 'Category II', 0, 'Unknown', 1, 1, '2025-03-27', 1, '2025-03-26', 0, NULL, 0, NULL, '2025-03-28', NULL, 'completed', '2025-05-20 12:33:12'),
(52, 51, 1, '2025-02-09', '2025-05-20 12:33:24', 'Cat', NULL, 'Owned by neighbor', 'James Brad', '09612307895', 'Alive', 'Yes', 'R Foot', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-02-09', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:33:24'),
(53, 52, 1, '2025-03-26', '2025-05-20 12:34:42', 'Cat', NULL, 'Owned by patient', 'Juana Torres', '09091235648', 'Alive', 'No', 'Right Hand', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-26', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:34:42'),
(54, 53, 1, '2025-02-09', '2025-05-20 12:35:03', 'Cat', NULL, 'Owned by patient', 'Alyssa Rosales', '09174270566', 'Alive', 'Yes', 'R Foot', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:35:03'),
(55, 54, 1, '2025-03-26', '2025-05-20 12:36:15', 'Cat', NULL, 'Owned by patient', 'Ramon Garcia', '09961234572', 'Alive', 'No', 'Right Knee', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:36:15'),
(56, 55, 1, '2025-02-09', '2025-05-20 12:37:00', 'Dog', NULL, 'Owned by patient', 'Jennifer Jimenez', '09211615479', 'Alive', 'Yes', 'L Arm, R Foot', 'Category III', 0, 'No', 1, 1, '2025-02-13', 1, '2025-02-09', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:37:00'),
(57, 56, 1, '2025-03-26', '2025-05-20 12:38:37', 'Dog', NULL, 'Owned by patient', 'Isabel Lopez', '09151233286', 'Alive', 'No', 'Left Upper Lip', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-26', 0, NULL, 0, NULL, '2025-03-27', NULL, 'completed', '2025-05-20 12:38:37'),
(58, 57, 1, '2025-02-09', '2025-05-20 12:38:58', 'Dog', NULL, 'Owned by patient', 'Trevor Walker', '09566591944', 'Alive', 'Yes', 'L Foot', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:38:58'),
(59, 58, 1, '2025-03-26', '2025-05-20 12:40:24', 'Cat', NULL, 'Owned by patient', 'Antonio Reyes', '09191235794', 'Alive', 'No', 'Left Hand', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-26', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:40:24'),
(60, 59, 1, '2025-02-09', '2025-05-20 12:40:50', 'Dog', NULL, 'Stray', NULL, NULL, 'Alive', 'Unknown', 'L Index Finger', 'Category II', 0, 'No', 1, 1, '2025-02-14', 1, '2025-02-09', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:40:50'),
(61, 60, 1, '2025-03-26', '2025-05-20 12:42:55', 'Dog', NULL, 'Owned by patient', 'Gloria Mendoza', '09071233359', 'Alive', 'No', 'Right Foot', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-26', 0, NULL, 0, NULL, '2025-03-27', NULL, 'completed', '2025-05-20 12:42:55'),
(62, 61, 1, '2025-02-09', '2025-05-20 12:43:14', 'Dog', NULL, 'Owned by patient', 'Janet Rodriguez', '09219461836', 'Alive', 'Yes', 'R Lower Back', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:43:14'),
(63, 62, 1, '2025-03-26', '2025-05-20 12:44:40', 'Dog', NULL, 'Owned by patient', 'Daniel Lopez', '09081235147', 'Alive', 'No', 'Right Leg', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:44:40'),
(64, 63, 1, '2025-02-10', '2025-05-20 12:45:16', 'Rat', NULL, 'Unknown', NULL, NULL, 'Unknown', 'Unknown', 'R Hand', 'Category II', 0, 'No', 1, 1, '2025-02-14', 1, '2025-02-10', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:45:16'),
(65, 64, 1, '2025-03-26', '2025-05-20 12:46:17', 'Dog', NULL, 'Owned by patient', 'Rosa Santos', '09991233205', 'Alive', 'No', 'Right Hand', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-26', 0, NULL, 0, NULL, '2025-03-27', NULL, 'completed', '2025-05-20 12:46:17'),
(66, 65, 1, '2025-03-23', '2025-05-20 12:48:06', 'Dog', NULL, 'Owned by patient', 'Manuel Rivera', '09171235130', 'Alive', 'No', 'Right Hand', 'Category II', 0, 'No', 1, 1, '2025-03-24', 1, '2025-03-23', 0, NULL, 0, NULL, '2025-03-24', NULL, 'completed', '2025-05-20 12:48:06'),
(67, 66, 1, '2025-03-25', '2025-05-20 12:51:49', 'Dog', NULL, 'Owned by patient', 'Teresa Cruz', '09161233702', 'Alive', 'No', 'Left Leg', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-25', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:51:49'),
(68, 67, 1, '2025-03-24', '2025-05-20 12:53:34', 'Cat', NULL, 'Owned by patient', 'Carlos Mendoza', '09061230074', 'Alive', 'No', 'Right Leg', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-24', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:53:34'),
(69, 68, 1, '2025-02-11', '2025-05-20 12:55:23', 'Rat', NULL, 'Unknown', NULL, NULL, 'Unknown', 'Unknown', 'R Arm', 'Category II', 0, 'No', 1, 1, '2025-02-15', 1, '2025-02-11', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:55:23'),
(70, 69, 1, '2025-03-26', '2025-05-20 12:55:31', 'Dog', NULL, 'Owned by patient', 'Elena Torres', '09151233988', 'Alive', 'No', 'Right Leg', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-26', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:55:31'),
(71, 70, 1, '2025-03-27', '2025-05-20 12:57:09', 'Cat', NULL, 'Owned by patient', 'Andres Garcia', '09091235260', 'Alive', 'No', 'Right Hand', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-27', 0, NULL, 0, NULL, '2025-03-27', NULL, 'completed', '2025-05-20 12:57:09'),
(72, 71, 1, '2025-02-11', '2025-05-20 12:58:09', 'Dog', NULL, 'Owned by patient', 'John Vincent Deoz', '09209020185', 'Alive', 'Yes', 'R Hand', 'Category II', 0, 'Yes', 1, 0, NULL, 1, '2025-02-11', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:58:09'),
(73, 72, 1, '2025-03-26', '2025-05-20 12:59:53', 'Cat', NULL, 'Owned by patient', 'Ligaya Lopez', '09971235003', 'Alive', 'No', 'Left Foot', 'Category II', 0, 'No', 1, 1, '2025-03-27', 0, '2025-03-26', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 12:59:53'),
(74, 73, 1, '2025-02-11', '2025-05-20 13:01:33', 'Cat', NULL, 'Owned by patient', 'Stephen Garcia', '09053761261', 'Alive', 'Yes', 'L Foot', 'Category II', 0, 'Yes', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:01:33'),
(75, 74, 1, '2025-03-25', '2025-05-20 13:03:21', 'Cat', NULL, 'Owned by patient', 'Jose Reyes', '09181235622', 'Alive', 'No', 'Right Foot', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-25', 1, 'Amoxicillin', 0, NULL, '2025-03-26', NULL, 'completed', '2025-05-20 13:03:21'),
(76, 75, 1, '2025-03-26', '2025-05-20 13:05:34', 'Dog', NULL, 'Owned by patient', 'Carmen Mendoza', '09161234540', 'Alive', 'No', 'Left Hand', 'Category II', 0, 'No', 1, 1, '2025-03-27', 1, '2025-03-26', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:05:34'),
(77, 76, 1, '2025-03-26', '2025-05-20 13:07:20', 'Dog', NULL, 'Stray', NULL, NULL, 'Alive', 'Unknown', 'Right Leg', 'Category II', 0, 'Unknown', 1, 1, '2025-03-28', 1, '2025-03-26', 1, 'Amoxicillin', 0, NULL, '2025-03-28', NULL, 'completed', '2025-05-20 13:07:20'),
(78, 77, 1, '2025-03-27', '2025-05-20 13:10:02', 'Cat', NULL, 'Owned by patient', 'Juana Garcia', '09961234851', 'Alive', 'No', 'Left Leg', 'Category II', 0, 'No', 1, 1, '2025-03-28', 1, '2025-03-27', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:10:02'),
(79, 78, 1, '2025-03-27', '2025-05-20 13:13:08', 'Cat', NULL, 'Stray', NULL, NULL, 'Alive', 'Unknown', 'Left Foot', 'Category II', 0, 'Unknown', 1, 1, '2025-03-28', 1, '2025-03-27', 0, NULL, 0, NULL, '2025-03-27', NULL, 'completed', '2025-05-20 13:13:08'),
(80, 79, 1, '2025-03-27', '2025-05-20 13:18:23', 'Cat', NULL, 'Stray', NULL, NULL, 'Alive', 'Unknown', 'Right Hand', 'Category II', 0, 'Unknown', 1, 1, '2025-03-27', 1, '2025-03-27', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:18:23'),
(81, 80, 1, '2025-03-26', '2025-05-20 13:21:14', 'Cat', NULL, 'Stray', NULL, NULL, 'Alive', 'Unknown', 'Right Palm', 'Category II', 0, 'Unknown', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:21:14'),
(82, 81, 1, '2025-03-26', '2025-05-20 13:23:31', 'Cat', NULL, 'Stray', NULL, NULL, 'Alive', 'Unknown', 'Left Leg', 'Category II', 0, 'Unknown', 1, 1, '2025-03-26', 1, '2025-03-27', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:23:31'),
(83, 82, 1, '2025-03-26', '2025-05-20 13:25:10', 'Cat', NULL, 'Owned by patient', 'Juan Santos', '09061234733', 'Alive', 'No', 'Right Foot', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:25:10'),
(84, 83, 1, '2025-03-23', '2025-05-20 13:27:10', 'Cat', NULL, 'Owned by patient', 'Gloria Rivera', '09181235218', 'Alive', 'No', 'Right Hand', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-23', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:27:10'),
(85, 84, 1, '2025-03-27', '2025-05-20 13:29:31', 'Cat', NULL, 'Owned by patient', 'Daniel Cruz', '09091233155', 'Alive', 'No', 'Right Leg', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-27', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:29:31'),
(86, 85, 1, '2025-03-27', '2025-05-20 13:31:02', 'Cat', NULL, 'Owned by patient', 'Rosa Garcia', '09981234984', 'Alive', 'No', 'Left Foot', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-27', 0, NULL, 0, NULL, '2025-03-28', NULL, 'completed', '2025-05-20 13:31:02'),
(87, 86, 1, '2025-03-27', '2025-05-20 13:33:19', 'Cat', NULL, 'Owned by patient', 'Renato Lopez', '09171234029', 'Alive', 'No', 'Anterior Chest', 'Category II', 0, 'No', 1, 1, '2025-03-27', 1, '2025-03-27', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:33:19'),
(88, 87, 1, '2025-02-11', '2025-05-20 13:35:10', 'Dog', NULL, 'Owned by neighbor', 'Joshua Ramirez', '09566085313', 'Alive', 'Yes', 'R Lower Leg', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-02-11', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:35:10'),
(89, 88, 1, '2025-03-27', '2025-05-20 13:35:11', 'Dog', NULL, 'Owned by patient', 'Teresa Mendoza', '09051235613', 'Alive', 'No', 'Left Face', 'Category II', 0, 'No', 1, 1, '2025-03-28', 1, '2025-03-27', 0, NULL, 0, NULL, '2025-03-28', NULL, 'completed', '2025-05-20 13:35:11'),
(90, 89, 1, '2025-03-27', '2025-05-20 13:36:55', 'Dog', NULL, 'Owned by patient', 'Carlos Garcia', '09161233322', 'Alive', 'No', 'Right Wrist', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:36:55'),
(91, 90, 1, '2025-02-12', '2025-05-20 13:37:02', 'Cat', NULL, 'Owned by patient', 'Roger Lizares', '09355049417', 'Alive', 'Yes', 'R Hand', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:37:02'),
(92, 91, 1, '2025-02-12', '2025-05-20 13:39:04', 'Dog', NULL, 'Owned by patient', 'James Garcia', '09612403612', 'Alive', 'No', 'L Hand', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-02-12', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:39:04'),
(93, 92, 1, '2025-03-27', '2025-05-20 13:39:17', 'Cat', NULL, 'Owned by patient', 'Elena Cruz', '09961235108', 'Alive', 'No', 'Right Leg', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-03-27', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:39:17'),
(94, 93, 1, '2025-03-27', '2025-05-20 13:40:42', 'Dog', NULL, 'Owned by patient', 'Emilio Reyes', '09181234804', 'Alive', 'No', 'Right Foot', 'Category II', 0, 'No', 1, 1, '2025-03-28', 0, NULL, 0, NULL, 0, NULL, '2025-03-29', NULL, 'completed', '2025-05-20 13:40:42'),
(95, 94, 1, '2025-02-13', '2025-05-20 13:41:51', 'Cat', NULL, 'Owned by patient', 'Joseph Duremdez', '09216566191', 'Alive', 'Yes', 'R Knee', 'Category II', 0, 'No', 1, 0, NULL, 1, '2025-02-13', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:41:51'),
(96, 95, 1, '2025-02-13', '2025-05-20 13:43:41', 'Cat', NULL, 'Owned by neighbor', 'Allen Ramirez', '09566085301', 'Alive', 'Yes', 'L Hand', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:43:41'),
(97, 96, 1, '2025-02-13', '2025-05-20 13:46:20', 'Dog', NULL, 'Owned by patient', 'Chrstine Ramirez', '09173101094', 'Alive', 'No', 'Buttocks', 'Category II', 0, 'No', 1, 1, '2025-02-17', 1, '2025-02-13', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:46:20'),
(98, 97, 1, '2025-02-13', '2025-05-20 13:49:33', 'Dog', NULL, 'Owned by patient', 'Anthony Dela Cruz', '09067192261', 'Alive', 'Yes', 'R Fore Arm', 'Category II', 0, 'Yes', 1, 1, '2025-02-15', 1, '2025-02-13', 0, NULL, 0, NULL, NULL, NULL, 'completed', '2025-05-20 13:49:33'),
(99, 98, 1, '2025-02-14', '2025-05-20 13:52:27', 'Cat', NULL, 'Owned by patient', 'Miguel Santino', '09211745793', 'Alive', 'Yes', 'R Hand', 'Category II', 0, 'No', 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, '', 'completed', '2025-05-21 00:12:08'),
(100, 99, 1, '2025-02-14', '2025-05-20 13:55:11', 'Dog', '', 'Stray', '', '', 'Unknown', 'Yes', 'Right Leg', 'Category II', 0, 'No', 1, 1, '2025-02-18', 1, '2025-02-14', 0, '', 0, '', NULL, '', 'completed', '2025-05-20 17:34:16'),
(101, 100, 1, '2025-03-21', '2025-05-21 01:53:03', 'Other', 'Snake', 'Unknown', NULL, NULL, 'Alive', 'Unknown', 'Face', 'Category III', 0, 'Unknown', 1, 0, NULL, 1, '2025-03-21', 0, NULL, 0, NULL, '2025-03-22', '', 'pending', '2025-11-27 03:16:24'),
(102, 101, 2, '2025-11-11', '2025-11-11 06:47:28', 'Dog', '', 'Stray', '', '', 'Alive', 'Unknown', 'right thigh', 'Category III', 0, 'No', 1, 1, '2025-11-12', 1, '2025-11-12', 0, '', 1, 'riverside national hospitality', '2025-11-11', 'comeback if you feel nothing', 'referred', '2025-11-11 06:50:30'),
(103, 102, 2, '2025-11-09', '2025-11-11 07:45:24', 'Cat', NULL, 'Owned by patient', NULL, NULL, 'Alive', 'No', 'right foot', 'Category II', 0, 'Unknown', 0, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 'observe cat for 14 days.', 'pending', '2025-11-11 07:45:24'),
(104, 103, NULL, '2025-11-17', '2025-11-19 05:42:32', 'Other', 'Snake', 'Unknown', NULL, NULL, 'Dead', 'No', 'Left leg', 'Category III', 0, 'Yes', 0, 1, '2025-11-07', 0, NULL, 0, NULL, 0, NULL, NULL, 'remarks', 'pending', '2025-11-19 05:42:32');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staffId` int(11) NOT NULL,
  `firstName` varchar(24) DEFAULT NULL,
  `lastName` varchar(30) DEFAULT NULL,
  `birthDate` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contactNumber` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staffId`, `firstName`, `lastName`, `birthDate`, `address`, `contactNumber`, `email`, `password`) VALUES
(1, 'Julie Ann', 'Panolino', '2003-06-19', 'Triad', '1234567890', 'japanolino.chmsu@gmail.com', '$2y$10$R0C33pFjgaX0tl6HsYS1ReIgNZWO7.MI.BRUU48PMqR.I5J90Q3Wq'),
(2, 'Anthony', 'Angeles', '2025-03-31', 'Pani', '1234567890', 'aaangeles.chmsu@gmail.com', '$2y$10$lOw2lxH4jYRSJe0E7KC2belRgVrlz4linM2IwyPumMrb80ypIVbS.');

-- --------------------------------------------------------

--
-- Table structure for table `vaccination_records`
--

CREATE TABLE `vaccination_records` (
  `vaccinationId` int(11) NOT NULL,
  `patientId` int(11) NOT NULL,
  `reportId` int(11) DEFAULT NULL,
  `exposureType` enum('PrEP','PEP') NOT NULL,
  `doseNumber` int(11) NOT NULL,
  `dateGiven` date NOT NULL,
  `vaccineName` varchar(100) DEFAULT NULL,
  `administeredBy` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vaccination_records`
--

INSERT INTO `vaccination_records` (`vaccinationId`, `patientId`, `reportId`, `exposureType`, `doseNumber`, `dateGiven`, `vaccineName`, `administeredBy`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 103, 104, 'PrEP', 1, '2025-11-27', 'test', 'test', 'test', '2025-11-26 17:07:26', '2025-11-26 17:07:26'),
(2, 103, 104, 'PEP', 2, '2025-11-29', 'Rabipur', 'testl', 'test', '2025-11-29 06:31:46', '2025-11-29 06:38:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`adminId`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `barangay_coordinates`
--
ALTER TABLE `barangay_coordinates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barangay` (`barangay`);

--
-- Indexes for table `classifications`
--
ALTER TABLE `classifications`
  ADD PRIMARY KEY (`classificationID`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`patientId`),
  ADD KEY `idx_name` (`lastName`,`firstName`),
  ADD KEY `idx_barangay` (`barangay`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`reportId`),
  ADD KEY `fk_patient` (`patientId`),
  ADD KEY `fk_staff` (`staffId`),
  ADD KEY `idx_biteDate` (`biteDate`),
  ADD KEY `idx_reportDate` (`reportDate`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staffId`);

--
-- Indexes for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  ADD PRIMARY KEY (`vaccinationId`),
  ADD KEY `fk_vaccination_patient` (`patientId`),
  ADD KEY `fk_vaccination_report` (`reportId`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `adminId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `barangay_coordinates`
--
ALTER TABLE `barangay_coordinates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `classifications`
--
ALTER TABLE `classifications`
  MODIFY `classificationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `patientId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `reportId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staffId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  MODIFY `vaccinationId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `fk_patient` FOREIGN KEY (`patientId`) REFERENCES `patients` (`patientId`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_staff` FOREIGN KEY (`staffId`) REFERENCES `staff` (`staffId`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  ADD CONSTRAINT `fk_vaccination_patient` FOREIGN KEY (`patientId`) REFERENCES `patients` (`patientId`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_vaccination_report` FOREIGN KEY (`reportId`) REFERENCES `reports` (`reportId`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
